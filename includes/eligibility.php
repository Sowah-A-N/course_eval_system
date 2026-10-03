<?php
/**
 * Course-evaluation eligibility rules (feature 2.2 — seminar audience).
 *
 * A student may evaluate a course when:
 *   - the course has an explicit roster (seminar_attendees) and the student is on it; OR
 *   - the course has NO roster and its audience scope matches the student:
 *       institution -> any active student
 *       department  -> same department (any level)
 *       cohort      -> same department AND level (default; regular courses)
 *
 * Regular courses default to course_audience='cohort' with no roster, so this
 * reduces to the original "dept + level must match" behaviour for them.
 */

require_once __DIR__ . '/../config/constants.php';

if (!function_exists('ces_course_eligibility_where')) {

    /**
     * SQL boolean expression (for a courses table aliased $a) that is true when
     * the student is eligible. Bind, in this order: student_id, dept_id, dept_id, level_id.
     */
    function ces_course_eligibility_where($a = 'c')
    {
        return "(
            EXISTS (SELECT 1 FROM seminar_attendees sa WHERE sa.course_id = {$a}.id AND sa.student_user_id = ?)
            OR (
                NOT EXISTS (SELECT 1 FROM seminar_attendees sa2 WHERE sa2.course_id = {$a}.id)
                AND (
                    {$a}.course_audience = 'institution'
                    OR ({$a}.course_audience = 'department' AND {$a}.department_id = ?)
                    OR ({$a}.course_audience = 'cohort' AND {$a}.department_id = ? AND {$a}.level_id = ?)
                )
            )
        )";
    }

    /** True if the given student may evaluate the given course. */
    function ces_student_can_evaluate($conn, $student_id, $dept_id, $level_id, $course_id)
    {
        $sql = "SELECT 1 FROM courses c WHERE c.id = ? AND " . ces_course_eligibility_where('c') . " LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, "iiiii", $course_id, $student_id, $dept_id, $dept_id, $level_id);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_fetch_row(mysqli_stmt_get_result($stmt)) ? true : false;
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /**
     * SQL scalar expression for the number of active students eligible for a course
     * (the response-rate denominator), audience- and roster-aware. $c is the courses
     * table alias in the surrounding query. Safe to use inside SUM(...) over courses.
     */
    function ces_course_denominator_sql($c = 'c')
    {
        $r = (int) ROLE_STUDENT;
        return "(CASE
            WHEN EXISTS (SELECT 1 FROM seminar_attendees sa WHERE sa.course_id = {$c}.id)
                THEN (SELECT COUNT(*) FROM seminar_attendees sa WHERE sa.course_id = {$c}.id)
            WHEN {$c}.course_audience = 'institution'
                THEN (SELECT COUNT(*) FROM user_details u WHERE u.role_id = {$r} AND u.is_active = 1)
            WHEN {$c}.course_audience = 'department'
                THEN (SELECT COUNT(*) FROM user_details u WHERE u.role_id = {$r} AND u.is_active = 1 AND u.department_id = {$c}.department_id)
            ELSE (SELECT COUNT(*) FROM user_details u WHERE u.role_id = {$r} AND u.is_active = 1 AND u.department_id = {$c}.department_id AND u.level_id = {$c}.level_id)
        END)";
    }

    /**
     * SQL scalar subquery for how many courses a student is eligible to evaluate,
     * audience- and roster-aware. $u is the user_details alias in the surrounding query.
     */
    function ces_student_denominator_sql($u = 'u')
    {
        return "(SELECT COUNT(*) FROM courses c WHERE
            EXISTS (SELECT 1 FROM seminar_attendees sa WHERE sa.course_id = c.id AND sa.student_user_id = {$u}.user_id)
            OR (
                NOT EXISTS (SELECT 1 FROM seminar_attendees sa2 WHERE sa2.course_id = c.id)
                AND (
                    c.course_audience = 'institution'
                    OR (c.course_audience = 'department' AND c.department_id = {$u}.department_id)
                    OR (c.course_audience = 'cohort' AND c.department_id = {$u}.department_id AND c.level_id = {$u}.level_id)
                )
            ))";
    }

    /**
     * Count of active students eligible for a course — the response-rate denominator.
     * Roster present -> roster size; otherwise the audience scope's population.
     */
    function ces_eligible_student_count($conn, $course_id)
    {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM seminar_attendees WHERE course_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $course_id);
        mysqli_stmt_execute($stmt);
        $roster = (int) (mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
        mysqli_stmt_close($stmt);
        if ($roster > 0) {
            return $roster;
        }

        $role = ROLE_STUDENT;
        $sql = "SELECT
                  CASE c.course_audience
                    WHEN 'institution' THEN (SELECT COUNT(*) FROM user_details u WHERE u.role_id = ? AND u.is_active = 1)
                    WHEN 'department'  THEN (SELECT COUNT(*) FROM user_details u WHERE u.role_id = ? AND u.is_active = 1 AND u.department_id = c.department_id)
                    ELSE               (SELECT COUNT(*) FROM user_details u WHERE u.role_id = ? AND u.is_active = 1 AND u.department_id = c.department_id AND u.level_id = c.level_id)
                  END AS n
                FROM courses c WHERE c.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiii", $role, $role, $role, $course_id);
        mysqli_stmt_execute($stmt);
        $n = (int) (mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
        mysqli_stmt_close($stmt);
        return $n;
    }
}
