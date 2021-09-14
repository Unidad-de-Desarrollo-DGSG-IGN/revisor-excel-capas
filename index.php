<?php
/****************************************************
Revisa que los datos ingresados en el Excel de Capas
existan en la base de datos.
El objetivo es evitar que fallen los scripts de
creación de capas, estilos y grupos de Geoserver.
- Require que exista el archivo capas.xlsx
****************************************************/
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
require_once 'config.php';
require_once 'xls2/Classes/PHPExcel/IOFactory.php';


// Conectando y seleccionado la base de datos  
$dbconn = pg_connect("host=".$sDBHostLAB." dbname=".$sDBNameLAB." user=".$sDBUsrLAB." password=".$sDBPswLAB)
    or die('No se ha podido conectar: ' . pg_last_error());


//Leer archivo fuente
$objPHPExcel = PHPExcel_IOFactory::load('capas.xlsx');

/******* INICIO REVISA CAPAS ********/
$sHoja = 'Capas';
$objPHPExcel->setActiveSheetIndex(1); //Hoja: Capas
$objWorksheet = $objPHPExcel->getSheet(1); //Hoja: Capas
$iHighestRow = $objWorksheet->getHighestRow();

$aCapas = Array();

for ($iRow = 2; $iRow <= $iHighestRow; $iRow++) {
	
	$aRow = Array();
	$aRow['workspace'] = trim($objWorksheet->getCell('A'.$iRow)->getValue());
	$aRow['almacen_datos'] = trim($objWorksheet->getCell('B'.$iRow)->getValue());
    $aRow['esquema'] = trim($objWorksheet->getCell('C'.$iRow)->getValue());
	$aRow['tabla'] = trim($objWorksheet->getCell('D'.$iRow)->getValue());
	$aRow['layerNamePrefix'] = trim($objWorksheet->getCell('E'.$iRow)->getValue());
	$aRow['nombre_capa'] = trim($objWorksheet->getCell('F'.$iRow)->getValue());
	$aRow['titulo_capa'] = trim($objWorksheet->getCell('G'.$iRow)->getValue());
	$aRow['palabras_clave'] = trim($objWorksheet->getCell('H'.$iRow)->getValue());
	$aRow['no_anunciado'] = trim($objWorksheet->getCell('I'.$iRow)->getValue());
	$aRow['srs'] = trim($objWorksheet->getCell('J'.$iRow)->getValue());
	$aRow['clave_primaria'] = trim($objWorksheet->getCell('K'.$iRow)->getValue());
	$aRow['campo_filtro'] = trim($objWorksheet->getCell('L'.$iRow)->getValue());
	$aRow['dominio_filtro'] = trim($objWorksheet->getCell('M'.$iRow)->getValue());
	$aRow['campos_publicar'] = trim($objWorksheet->getCell('N'.$iRow)->getValue());
    $aRow['resumen'] = trim($objWorksheet->getCell('O'.$iRow)->getValue());
    $aRow['estilo'] = trim($objWorksheet->getCell('P'.$iRow)->getValue());
    $aRow['estilo_recurso'] = trim($objWorksheet->getCell('Q'.$iRow)->getValue());
    
	if (!empty($aRow['workspace']) || !empty($aRow['almacen_datos']) || !empty($aRow['esquema']) ||
	    !empty($aRow['tabla']) || !empty($aRow['nombre_capa']) || !empty($aRow['titulo_capa'])) {
	
		if (empty($aRow['workspace'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Espacio de trabajo');
		} elseif (empty($aRow['almacen_datos'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Almacen de datos');
		} elseif (empty($aRow['esquema'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Esquema');
		} elseif (empty($aRow['tabla'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Tabla');
		} elseif (empty($aRow['nombre_capa'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Nombre');
		} elseif (empty($aRow['titulo_capa'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Titulo');
		} elseif (empty($aRow['srs'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo SRS');
		} elseif (empty($aRow['clave_primaria'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Clave primaria');
		} elseif (!empty($aRow['campo_filtro']) && empty($aRow['dominio_filtro']) && $aRow['dominio_filtro'] !== '0') {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Dominio de filtro');
		} elseif (!empty($aRow['dominio_filtro']) && empty($aRow['campo_filtro'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Campo de filtro');
		} else {
			
			$identificadorCapa = $aRow['workspace'].':'.$aRow['nombre_capa'];
			
			if (!empty($aRow['campos_publicar'])) {
				if (!preg_match('/geom/', $aRow['campos_publicar'])) {
					muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'No tiene definido el campo geom en "Campos a publicar"');
				} else {
					$sSelectTest = "select " . $aRow['campos_publicar'] . " from " . $aRow['esquema'] . "." . $aRow['tabla'] . ' limit 1';
					$result = pg_query($sSelectTest) or muestraError($sHoja, $iRow, pg_last_error() . ' - CONSULTA: ' . $sSelectTest);
					if ($result && pg_num_rows($result) <= 0) {
						muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'La consulta no devolvió resultados: '.$sSelectTest);
					}
				}
			} else {
				$sSelectTest = "select * from " . $aRow['esquema'] . "." . $aRow['tabla'] . ' limit 1';
				$result = pg_query($sSelectTest) or muestraError($sHoja, $iRow, pg_last_error() . ' - CONSULTA: ' . $sSelectTest);
				if ($result && pg_num_rows($result) <= 0) {
					muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'La consulta no devolvió resultados: '.$sSelectTest);
				}
			}
			
			if (!empty($aRow['campo_filtro'])) {
				$sSelectTest = "select * from " . $aRow['esquema'] . "." . $aRow['tabla'] . " where " . $aRow['campo_filtro'] . " = '" . $aRow['dominio_filtro'] . "' limit 1";
				$result = pg_query($sSelectTest) or muestraError($sHoja, $iRow, pg_last_error() . ' - CONSULTA: ' . $sSelectTest);
				if ($result && pg_num_rows($result) <= 0) {
					muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'La consulta no devolvió resultados: '.$sSelectTest);
				}
			}
			
			$sAux = "SELECT geom
					 FROM (".str_replace('limit 1', '', $sSelectTest).") AS tbl
					 where ST_Within(geom, ST_MakeEnvelope(-180, -90, 180, 90, ".$aRow['srs'].")::geometry) = false
					 limit 1";
			$result = pg_query($sAux) or muestraError($sHoja, $iRow, pg_last_error() . ' - CONSULTA: ' . $sAux);
			if ($result && pg_num_rows($result) > 0) {
				muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'La tabla contiene geometrías no válidas');
			}
			
			if (!empty($aRow['estilo'])) {
				$aAux = explode(',', $aRow['estilo']);
				foreach ($aAux as $sRecurso) {
					if (!file_exists(ESTILOS_DIR.$aRow['workspace'].'/'.$sRecurso.'.sld')) {
						muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'No existe el estilo '.$sRecurso.'.sld');
					}
				}
			}
			if (!empty($aRow['estilo_recurso'])) {
				$aAux = explode(',', $aRow['estilo_recurso']);
				foreach ($aAux as $sRecurso) {
					if (!file_exists(ESTILOS_DIR.$aRow['workspace'].'/'.$sRecurso)) {
						muestraErrorCapa($sHoja, $iRow, $identificadorCapa, 'No existe el recurso '.$sRecurso);
					}
				}
			}
			
		}
		
		$aCapas[$aRow['workspace'] . ':' . $aRow['nombre_capa']] = $aRow;
	
	}
	
}

pg_close($dbconn);

/******* FIN REVISA CAPAS ********/


/******* INICIO REVISA GRUPO DE CAPAS ********/
$sHoja = 'Grup de capas';
$objPHPExcel->setActiveSheetIndex(2); //Hoja: Grupos de capas
$objWorksheet = $objPHPExcel->getSheet(2); //Hoja: Grupos de capas
$iHighestRow = $objWorksheet->getHighestRow();

for ($iRow = 2; $iRow <= $iHighestRow; $iRow++) {
    
    $aRow = Array();
	$aRow['name'] = trim($objWorksheet->getCell('A'.$iRow)->getValue());
	$aRow['mode'] = trim($objWorksheet->getCell('B'.$iRow)->getValue());
    $aRow['title'] = trim($objWorksheet->getCell('C'.$iRow)->getValue());
    $aRow['workspace'] = trim($objWorksheet->getCell('D'.$iRow)->getValue());
    $aRow['srs'] = trim($objWorksheet->getCell('E'.$iRow)->getValue());
    $aRow['abstract'] = trim($objWorksheet->getCell('F'.$iRow)->getValue());
    $aRow['layers'] = trim($objWorksheet->getCell('G'.$iRow)->getValue());
    $aRow['styles'] = trim($objWorksheet->getCell('H'.$iRow)->getValue());
    
	if (!empty($aRow['name']) || !empty($aRow['mode']) || !empty($aRow['title']) ||
	    !empty($aRow['workspace']) || !empty($aRow['srs']) || !empty($aRow['layers'])) {
			
		if (empty($aRow['name'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Name');
		} elseif (empty($aRow['mode'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Mode');
		} elseif (empty($aRow['title'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Title');
		} elseif (empty($aRow['workspace'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Workspace');
		} elseif (empty($aRow['srs'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo SRS');
		} elseif (empty($aRow['layers'])) {
			muestraError($sHoja, $iRow, 'No tiene definido el campo Layer list');
		}
		
		$aRow['layers'] = explode(',', $aRow['layers']);
		
		//Controla que las capas del grupo existan como capas
		foreach ($aRow['layers'] as $sLayer) {
			if (!isset($aCapas[$aRow['workspace'] . ':' . $sLayer])) {
				muestraError($sHoja, $iRow, 'La capa ' . $aRow['workspace'] . ':' . $sLayer . ' no existe en la hoja Capas');
			}
		}
		
		//Controla que los estilos existan para las capas
		if (!empty($aRow['styles'])) {
			$aRow['styles'] = explode(',', $aRow['styles']);
			
			if (count($aRow['layers']) != count($aRow['styles'])) {
				muestraError($sHoja, $iRow, 'La cantidad de capas no coincide con la cantidad de estilos');
			} else {
				
				foreach ($aRow['styles'] as $iKey => $sStyle) {
					$aCapa = $aCapas[$aRow['workspace'] . ':' . $aRow['layers'][$iKey]];
					if ($aCapa['estilo'] != $sStyle) {
						muestraError($sHoja, $iRow, 'El estilo ' . $sStyle . ' no coincide con el estilo de su capa ' . $aRow['workspace'] . ':' . $aRow['layers'][$iKey]);
					}
				}
				
			}
			
		}
		
	}
    
}
/******* FIN REVISA GRUPO DE CAPAS ********/



function muestraError($sHoja, $iFila, $sMensaje) {
	echo 'HOJA ' . $sHoja . ' - FILA ' . $iFila . ': ' . $sMensaje."\n";
}

function muestraErrorCapa($sHoja, $iFila, $identificadorCapa, $sMensaje) {
	echo 'HOJA ' . $sHoja . ' - FILA ' . $iFila . ' - CAPA ' . $identificadorCapa . ': ' . $sMensaje."\n";
}

?>
