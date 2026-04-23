<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Reorder Questions';
$query="SELECT * FROM evaluation_questions WHERE is_active=1 ORDER BY display_order,question_id";
$result=mysqli_query($conn,$query);
$questions=[];
while($row=mysqli_fetch_assoc($result))$questions[]=$row;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
$order_data=json_decode($_POST['order_data']??'[]',true);
if(is_array($order_data)){
foreach($order_data as $item){
$question_id=intval($item['id']??0);
$new_order=intval($item['order']??1);
if($question_id>0){
$query="UPDATE evaluation_questions SET display_order=? WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ii",$new_order,$question_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}
}
$_SESSION['flash_message']='Questions reordered successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}
}
require_once '../../includes/header.php';
?>
<style>
.reorder-container{max-width:900px;margin:0 auto}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
.questions-reorder{background:white;border-radius:8px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.question-reorder-item{padding:15px;margin:10px 0;background:#f8f9fa;border-radius:8px;border-left:4px solid #667eea;cursor:move;display:flex;align-items:center;transition:all 0.3s}
.question-reorder-item:hover{background:#e9ecef;transform:translateX(5px)}
.drag-handle{font-size:24px;margin-right:15px;color:#999}
.question-order-num{font-size:20px;font-weight:bold;color:#667eea;margin-right:15px;min-width:40px}
.question-reorder-text{flex:1;font-size:15px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
</style>
<div class="reorder-container">
<div class="page-header">
<h1>Reorder Questions</h1>
<p>Drag and drop to change the order of questions</p>
</div>
<div class="info-box">
<strong>ℹ️ Instructions:</strong> Drag questions up or down to reorder them. Questions are displayed to students in this order. Click "Save Order" when done.
</div>
<form method="POST" id="reorderForm">
<?php csrf_token_input();?>
<input type="hidden" name="order_data" id="order_data" value="">
<div class="questions-reorder" id="questionsList">
<?php $order=1;foreach($questions as $question): ?>
<div class="question-reorder-item" data-id="<?php echo $question['question_id'];?>">
<div class="drag-handle">⋮⋮</div>
<div class="question-order-num"><?php echo $order++;?></div>
<div class="question-reorder-text"><?php echo htmlspecialchars($question['question_text']);?></div>
</div>
<?php endforeach;?>
</div>
<div style="margin-top:30px;text-align:center">
<button type="submit" class="btn btn-primary" onclick="return saveOrder()">Save Order</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" integrity="sha256-nSMFpCLCHbzkADPnLVIhA0lxJi6TcqIf4X1JWMBv+6A=" crossorigin="anonymous"></script>
<script>
const questionsList=document.getElementById('questionsList');
Sortable.create(questionsList,{
animation:150,
handle:'.drag-handle',
ghostClass:'sortable-ghost',
onEnd:function(){
updateOrderNumbers();
}
});
function updateOrderNumbers(){
const items=questionsList.querySelectorAll('.question-reorder-item');
items.forEach((item,index)=>{
item.querySelector('.question-order-num').textContent=index+1;
});
}
function saveOrder(){
const items=questionsList.querySelectorAll('.question-reorder-item');
const orderData=[];
items.forEach((item,index)=>{
orderData.push({
id:parseInt(item.dataset.id),
order:index+1
});
});
document.getElementById('order_data').value=JSON.stringify(orderData);
return true;
}
</script>
<?php require_once '../../includes/footer.php';?>
