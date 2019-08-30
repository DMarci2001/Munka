<?php
if(isset($_POST['savemodify']) && $_POST['savemodify']==true){
	$data = "";
	$labString = "";
	//Értelmezem a POST információkat
	if($_POST['csg_pack']==1) $data.= ",csomag_1";
	if($_POST['csg_pack']==2) $data.= ",csomag_2";
	//az alapnál vizsgálnom kell a páciens nemét is:
	if($_POST['tumor_pack']==1 && $_POST['neme']==2) $data.= ",alap_tumor_no";
	if($_POST['tumor_pack']==1 && $_POST['neme']==1) $data.= ",alap_tumor_ferfi";
	if($_POST['tumor_pack']==2) $data.= ",kieg. tumor_csg2";
	if($_POST['tumor_pack']==3) $data.= ",telj. tumor_csg1";
	//A checkboxonál vizsgálnom kell, hogy létezik-e:
	if(isset($_POST['nagylabor']) && $_POST['nagylabor']==1) $data.= ",kieg. nagylabor";
	if(isset($_POST['pajzsmirigy']) && $_POST['pajzsmirigy']==1) $data.= ",pajzsmirigy_labor";
	
	//clear-elem a string adat sort, majd tömbösítem:
	$data = substr($data, 1);
	$data = explode(",",$data);
	
	$key = array_search("csomag_1",$data);
	if($key!==FALSE) $modData[0] = "X";
	else $modData[0] = "";
	$key = "";
	$key = array_search("csomag_2",$data);
	if($key!==FALSE) $modData[1] = "X";
	else $modData[1] = "";
	$key = "";
	$key = array_search("alap_tumor_no",$data);
	if($key!==FALSE) $modData[2] = "X";
	else $modData[2] = "";
	$key = "";
	$key = array_search("alap_tumor_ferfi",$data);
	if($key!==FALSE) $modData[3] = "X";
	else $modData[3] = "";
	$key = "";
	$key = array_search("kieg. nagylabor",$data);
	if($key!==FALSE) $modData[4] = "X";
	else $modData[4] = "";
	$key = "";
	$key = array_search("kieg. tumor_csg2",$data);
	if($key!==FALSE) $modData[5] = "X";
	else $modData[5] = "";
	$key = "";
	$key = array_search("telj. tumor_csg1",$data);
	if($key!==FALSE) $modData[6] = "X";
	else $modData[6] = "";
	$key = "";
	$key = array_search("pajzsmirigy_labor",$data);
	if($key!==FALSE) $modData[7] = "X";
	else $modData[7] = "";
	$key =	"";
	
	require_once("../Classes/PHPExcel.php");
	require_once "../Classes/PHPExcel/IOFactory.php";
	
	$objPHPExcel = PHPExcel_IOFactory::load("módosított debrecen - laborkérő.xlsx");
	$objPHPExcel->setActiveSheetIndex(0);
	$row = $objPHPExcel->getActiveSheet()->getHighestRow()+1;
	//echo $row;
	
	$neme = ($_POST['neme']==1?"F":"N");
	$objPHPExcel->getActiveSheet()->SetCellValue('A'.$row, $_POST['nev']);
	$objPHPExcel->getActiveSheet()->SetCellValue('B'.$row, $neme);
	$objPHPExcel->getActiveSheet()->SetCellValue('C'.$row, $modData[0]);
	$objPHPExcel->getActiveSheet()->SetCellValue('D'.$row, $modData[1]);
	$objPHPExcel->getActiveSheet()->SetCellValue('F'.$row, $modData[2]);
	$objPHPExcel->getActiveSheet()->SetCellValue('G'.$row, $modData[3]);
	$objPHPExcel->getActiveSheet()->SetCellValue('I'.$row, $modData[4]);
	$objPHPExcel->getActiveSheet()->SetCellValue('J'.$row, $modData[5]);
	$objPHPExcel->getActiveSheet()->SetCellValue('K'.$row, $modData[6]);
	$objPHPExcel->getActiveSheet()->SetCellValue('L'.$row, $modData[7]);
	$objPHPExcel->getActiveSheet()->SetCellValue('R'.$row, date("Y.m.d H:i:s",strtotime("Now")));
	$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
	$objWriter->save('módosított debrecen - laborkérő.xlsx');

	die();
}

if(isset($_POST['savemodifySP']) && $_POST['savemodifySP']==true){
	$data = "";
	//Értelmezem a POST információkat
	if($_POST['csg_pack']==1) $data.= ",csomag_1";
	if($_POST['csg_pack']==2) $data.= ",csomag_2";
	//az alapnál vizsgálnom kell a páciens nemét is:
	if($_POST['tumor_pack']==1 && $_POST['neme']==2) $data.= ",alap_tumor_no";
	if($_POST['tumor_pack']==1 && $_POST['neme']==1) $data.= ",alap_tumor_ferfi";
	if($_POST['tumor_pack']==2) $data.= ",kieg. tumor_csg2";
	if($_POST['tumor_pack']==3) $data.= ",telj. tumor_csg1";
	//A checkboxonál vizsgálnom kell, hogy létezik-e:
	if(isset($_POST['nagylabor']) && $_POST['nagylabor']==1) $data.= ",kieg. nagylabor";
	if(isset($_POST['pajzsmirigy']) && $_POST['pajzsmirigy']==1) $data.= ",pajzsmirigy_labor";
	if(isset($_POST['abi']) && $_POST['abi']) $data.=",abi";
	if(isset($_POST['pajzsmirigyuh']) && $_POST['pajzsmirigyuh']) $data.=",pajzsmirigy_uh";
	if(isset($_POST['carotis']) && $_POST['carotis']) $data.=",carotis_vizsg";
	//clear
	$data = substr($data, 1);
	$data = explode(",",$data);
	
	$key = array_search("csomag_1",$data);
	if($key!==FALSE) $modData[0] = "X";
	else $modData[0] = "";
	$key = "";
	$key = array_search("csomag_2",$data);
	if($key!==FALSE) $modData[1] = "X";
	else $modData[1] = "";
	$key = "";
	$key = array_search("alap_tumor_no",$data);
	if($key!==FALSE) $modData[2] = "X";
	else $modData[2] = "";
	$key = "";
	$key = array_search("alap_tumor_ferfi",$data);
	if($key!==FALSE) $modData[3] = "X";
	else $modData[3] = "";
	$key = "";
	$key = array_search("kieg. nagylabor",$data);
	if($key!==FALSE) $modData[4] = "X";
	else $modData[4] = "";
	$key = "";
	$key = array_search("kieg. tumor_csg2",$data);
	if($key!==FALSE) $modData[5] = "X";
	else $modData[5] = "";
	$key = "";
	$key = array_search("telj. tumor_csg1",$data);
	if($key!==FALSE) $modData[6] = "X";
	else $modData[6] = "";
	$key = "";
	$key = array_search("pajzsmirigy_labor",$data);
	if($key!==FALSE) $modData[7] = "X";
	else $modData[7] = "";
	$key =	"";
	
	//Vizsgálatok:
	$key = array_search("abi",$data);
	if($key!==FALSE) $modData[8] = "X";
	else $modData[8] = "";
	$key =	"";
	$key = array_search("pajzsmirigyuh",$data);
	if($key!==FALSE) $modData[9] = "X";
	else $modData[9] = "";
	$key =	"";
	$key = array_search("carotis",$data);
	if($key!==FALSE) $modData[10] = "X";
	else $modData[10] = "";
	$key =	"";
	
	require_once("../Classes/PHPExcel.php");
	require_once "../Classes/PHPExcel/IOFactory.php";
	
	$objPHPExcel = PHPExcel_IOFactory::load("módosított debrecen - sétálólap.xlsx");
	$objPHPExcel->setActiveSheetIndex(0);
	$row = $objPHPExcel->getActiveSheet()->getHighestRow()+1;
	//echo $row;
	
	$neme = ($_POST['neme']==1?"F":"N");
	$objPHPExcel->getActiveSheet()->SetCellValue('A'.$row, $_POST['nev']);
	$objPHPExcel->getActiveSheet()->SetCellValue('B'.$row, $neme);
	$objPHPExcel->getActiveSheet()->SetCellValue('C'.$row, $modData[0]);
	$objPHPExcel->getActiveSheet()->SetCellValue('D'.$row, $modData[1]);
	$objPHPExcel->getActiveSheet()->SetCellValue('F'.$row, $modData[2]);
	$objPHPExcel->getActiveSheet()->SetCellValue('G'.$row, $modData[3]);
	$objPHPExcel->getActiveSheet()->SetCellValue('I'.$row, $modData[4]);
	$objPHPExcel->getActiveSheet()->SetCellValue('J'.$row, $modData[5]);
	$objPHPExcel->getActiveSheet()->SetCellValue('K'.$row, $modData[6]);
	$objPHPExcel->getActiveSheet()->SetCellValue('L'.$row, $modData[7]);
	//Vizsgálatok:
	$objPHPExcel->getActiveSheet()->SetCellValue('H'.$row, $modData[8]);
	$objPHPExcel->getActiveSheet()->SetCellValue('M'.$row, $modData[9]);
	$objPHPExcel->getActiveSheet()->SetCellValue('Q'.$row, $modData[10]);
	$objPHPExcel->getActiveSheet()->SetCellValue('R'.$row, date("Y.m.d H:i:s",strtotime("Now")));
	$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
	$objWriter->save('módosított debrecen - sétálólap.xlsx');

	die();
}

?>