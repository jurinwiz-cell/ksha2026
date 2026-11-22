<?php
$db = new mysqli('localhost', 'root', '1@tkghlwnxor', 'socialhousing');
$sql = "SELECT * FROM stock WHERE address LIKE '%상계동 1125%' OR location LIKE '%상계동 1125%'";
$result = $db->query($sql);
$rows = [];
while($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$db->close();
?>
