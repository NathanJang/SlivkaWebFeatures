<?php
header('Content-type: text/html; charset=utf-8');
require_once "./ajax/PointsCenter.php";
$points_center = new \Slivka\PointsCenter();
$fellows = $points_center->getFellows();
?>
<html>
<body>
	<table width="100%" border="1" cellspacing="0" cellpadding="10pt">
		<thead>
			<tr>
				<th>Name</th>
				<th>Position</th>
				<th>About</th>
				<th>Photo</th>
			</tr>
		</thead>
		<tbody>
<?php
for ($ii = 0; $ii < count($fellows); $ii++) {
    ?>
			<tr>
				<td><?= $fellows[$ii]["full_name"] ?></td>
				<td><?= $fellows[$ii]["position"] ?></td>
				<td><?= $fellows[$ii]["about"] ?></td>
				<td style="padding:0; width:100px;"><img src="./img/slivkans/<?= $fellows[$ii]["photo"] ?>" width="100px" /></td>
			</tr>
    <?php
}
?>
		</tbody>
	</table>
</body>
</html>
