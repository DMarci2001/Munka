<?php
if( $_SESSION["helyszindata"]["id"] != 11 ) header( "Location:index.php" );
if(isset($_POST['quicklogin']))
{
	
	$request = sql_query( "SELECT * FROM felhasznalok WHERE email = ? ", array( $_POST['email'] ));
	$numb = $request->rowCount();
	if($numb > 0)
	{
		if( isset($_POST['emailError'])) unset( $_POST['emailError'] );
		$result = sql_fetch_array( $request );
		$_SESSION['previousUser'] = $result['id'];
		header("Location:index.php?page=idopontfoglalas");
	}
	else $_POST['emailError'] = "Hibás e-mail cím!";
}
?>
<div class = "fejlecdiv" style = "background:#9d0102">HungáriaMed M - Időpont foglalás</div>
<div style = "display:block">
	<div style = "margin:auto;text-align:center;display:inline-block">
		<form method = "POST">
		<input type = "hidden" name = "quicklogin" value = "1">
		<table>
			<tr>
				<td>E-mail cím:</td><td><input <?php echo (isset($_POST['quicklogin']) && $numb == 0 ? 'style="border:1px solid red"' : "") ?> type = "textbox" name = "email" class = "design-put" /></td>
				<td style = "color:red"><?php if( isset( $_POST['quicklogin'] ) && $numb == 0 ) echo $_POST['emailError'] ?></td>
			</tr>
			<tr><td colspan = "2"><button class = "main-page-button" style = "margin-top:20px">Bejelentkezés</button></td></tr>
		</table>
		</form>
	</div>
</div>