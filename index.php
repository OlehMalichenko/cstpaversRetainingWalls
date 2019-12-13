<?php
ini_set('max_execution_time', 900);

header('Content-type: text/plain; charset=utf-8');
require_once 'phpQuery.php';

function curlstart($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function parser($url) {
    // устанавливаем соединение
    $html = curlstart($url);
    $pqHTML = phpquery::newDocument($html);
            
    // объявляем основной массив
    $arrAll = array(); 
    $pages = $pqHTML->find('#menu-item-201 ul li');
    
    //create main folder
    $nameMainFolder = $pqHTML->find('#menu-item-201 a .wpmega-link-title')->getString()[0];
    mkdir($nameMainFolder);
    
    $id = 0;
    
    // round by pages
    foreach ($pages as $page) {
        $id++;
        $pqPage = pq($page);
        
        // link on page product
        $linkPage = $pqPage->find('a')->attr('href');    
      
        // get array after parse one page
        $arrPage = parseProductPage($linkPage, $nameMainFolder);
        $arrPage['id'] = $id;
        $arrPage['link page of paver'] = $linkPage;
        
        // wen page have not name product
        if ($arrPage['Name wall'] == NULL) {continue;}
        
        $arrAll[] = $arrPage;
    }
    var_dump($arrAll);
    
    writeToExcel($arrAll);
}

function parseProductPage($linkPage, $nameMainFolder) {
    // устанавливаем соединение
    $html = curlstart($linkPage);
    $pqHTML = phpquery::newDocument($html);

    // declaration array for one page
    $arrPage = getArrPage();
    // id & link added in up function 'parser()'
    
    // get name & description product
    $namePaver = $pqHTML->find('#column-right .page h1')->getString()[0];
    if($namePaver == NULL) {return;}   
    $arrPage['Name wall'] = $namePaver;
    $description = $pqHTML->find('#column-right .page p')->getString()[0];    
    $arrPage['Description'] = $description;    
    
    // create "ProductFolder"
    $pathToProduktFolder = $nameMainFolder.'/'.$namePaver;
    mkdir($pathToProduktFolder, 0777);
    
    // find main image, create Name, create folders, add imageName to arrPage & put img in folder
    $arrImageNameAndLink = findImage($pqHTML, $pathToProduktFolder, $namePaver);
    $arrImageName = $arrImageNameAndLink['name'];
    $arrImageLink = $arrImageNameAndLink['link'];
    $imageNameCombined = $arrImageName[0];
    if(count($arrImageName)>1){
        $imageNameCombined = $imageNameCombined.PHP_EOL.$arrImageName[1];
    }
    $imageLinkCombined = $arrImageLink[0];
    if(count($arrImageLink)>1){
        $imageLinkCombined = $imageLinkCombined.PHP_EOL.$arrImageLink[1];
    }
    $arrPage['Name image'] = $imageNameCombined; 
    $arrPage['Link image'] = $imageLinkCombined;
    
    // find colors
    $arrColorsInPage = findColors($pqHTML, $pathToProduktFolder, $namePaver);
    $arrPage['Colors name'] = $arrColorsInPage['names'];
    $arrPage['Links to color images'] = $arrColorsInPage['links'];
    $arrPage['Name color in folder'] = $arrColorsInPage['namesF'];
    
    // find sizes
    $arrPage['Size'] = findSizes($pqHTML, $pathToProduktFolder);
    
    // find options
    $arrPatterns = findPatterns($pqHTML, $pathToProduktFolder, $namePaver);
    $arrPage['Number of patterns'] = $arrPatterns['Number of patterns'];
    $arrPage['Has guide'] = $arrPatterns['Has guide'];
    
    //find literature
    $arrPage['Literature link'] = findLiterature($pqHTML, $pathToProduktFolder, $namePaver);  
    
    return $arrPage;
}

function findLiterature($pqHTML, $pathToProduktFolder, $namePaver){
    $h2s = $pqHTML->find('#imgpnl');
    $literatureLink = 'Not';
    foreach ($h2s as $h){
        $hPq = pq($h)->find('h2:first');
        $marker = strpos($hPq, 'Literature Downloads');
        if($marker == FALSE or $marker == NULL){
            continue;
        }
        $link = $hPq->next('p')->find('a')->attr('href');
        $literatureLink = $link;
        $n = $hPq->next('p')->text();
        $name = (str_replace(' ', '', $n)) .'-Literature'. '.pdf';
        $pdf = file_get_contents($link);
        $path = $pathToProduktFolder . '/' . $name;
        file_put_contents($path, $pdf);     
    }
    return $literatureLink;
}

function findPatterns($pqHTML, $pathToProduktFolder, $namePaver) {
    $arrPatterns = array();

    $pathToPatternFolder = $pathToProduktFolder . '/' . 'Patterns';
    mkdir($pathToPatternFolder);

    $divImgAlt = $pqHTML->find("#column-right div img");
    $count = 0;
    foreach ($divImgAlt as $h) {
        $div = pq($h);
        $alt = $div->attr('alt');
        $altPos = strpos($alt, 'Concrete Paver Patterns');
        if ($altPos !== FALSE) {
            $count++;
            $link = $div->attr('src');
            $img = file_get_contents($link);
            $path = $pathToPatternFolder . '/' . "$count" . '.png';
            file_put_contents($path, $img);
        }
    }

    $pGuide = $pqHTML->find("#column-right p a");
    foreach ($pGuide as $pA) {
        $pApq = pq($pA);
        $pAPos = strpos($pApq, 'Download Pattern Guide');
        if ($pAPos !== FALSE) {
            $linkGuide = $pApq->attr('href');
            $guide = file_get_contents($linkGuide);
            $pathGuide = $pathToPatternFolder . '/' . "$namePaver" . '- PatternGuide.pdf';
            file_put_contents($pathGuide, $guide);
            $arrPatterns['Has guide'] = $linkGuide;
        }
    }
    if ($count != 0) {
        $arrPatterns['Number of patterns'] = "$count";
    } else {
        $arrPatterns['Number of patterns'] = 'has not patterns';
        $arrPatterns['Has guide'] = 'Not';
    }
    return $arrPatterns;
}

function findSizes($pqHTML, $pathToProduktFolder) {
    $sizesComplited = '';
    
    $rightArea = $pqHTML->find("#column-right div");
    $pathToSizeFolder = $pathToProduktFolder . '/' . 'Size';
    mkdir($pathToSizeFolder);
    
    $count = 1;
    foreach ($rightArea as $sss) {
        $pq = pq($sss);
        $style = $pq->attr('style');
                
        $markerBackgound = strpos($style, 'background-color:#7e564a');
        $markerImage = strpos($style, 'background-image:url(');
        $markerImage1 = strpos($pq, 'float:left; margin-top:4px; margin-right:10px;');
              
        if ($markerImage1 != FALSE) {
            $pq1 = $pq->find('> div');
            $link1 = $pq1->find('img')->attr('src');            
            if ($link1 != NULL) {               
                $img1 = file_get_contents($link1);
                $arrSizeAndName = getSizeAndName($pq1, $count);
                $sizesComplited = $sizesComplited . PHP_EOL . $arrSizeAndName['sizesComplited'];
                $nameSizeForFile = $arrSizeAndName['nameSizeForFile'];
                $path = $pathToSizeFolder . '/' . $nameSizeForFile;
                file_put_contents($path, $img1);
                $count++;
                continue;
            }
        }

        if ($markerImage != FALSE) { 
            $link = getClipString($style, 'http://', '.jpg');
            if ($link != NULL) {
                $img = file_get_contents($link);
                $arrSizeAndName = getSizeAndName($pq, $count);
                $sizesComplited = $sizesComplited . PHP_EOL . $arrSizeAndName['sizesComplited'];
                $nameSizeForFile = $arrSizeAndName['nameSizeForFile'];
                $path = $pathToSizeFolder . '/' . $nameSizeForFile;
                file_put_contents($path, $img);
                $count++;
                continue;
            }
        }

        if ($markerBackgound != FALSE) {
            $width = getClipString($style, 'width', ';');
            $height = getClipString($style, 'height', ';');            
            $widthInt = getIntFromString($width);
            $heightInt = getIntFromString($height);           
            $arrSizeAndName = getSizeAndName($pq, $count);
            $sizesComplited = $sizesComplited.PHP_EOL.$arrSizeAndName['sizesComplited'];
            $nameSizeForFile = $arrSizeAndName['nameSizeForFile'];           
            $path = $pathToSizeFolder.'/'.$nameSizeForFile; 
            $image = imagecreate($widthInt, $heightInt);
            $arrcollor = hexToRgb('#7e564a');
            imagecolorallocate($image, $arrcollor['red'], $arrcollor['green'], $arrcollor['blue']);
            imagepng($image,$path);
            imagedestroy($image);
            $count++;
        } 
    }   
    return $sizesComplited;
}

function findColors($pqHTML, $pathToProduktFolder, $namePaver){
    $arrColoreNameB = array();
    $arrColorLinkB = array();
    $arrColoreNameFileB = array();
    $colorsArea = $pqHTML->find('#column-right #colors div');
    
    // create Color folder
    $pathToColorFolder = $pathToProduktFolder . '/' . 'Color';
    mkdir($pathToColorFolder);
    
    foreach ($colorsArea as $im) {
        $pq = pq($im);
        $forNameColor = $pq->find('p')->getString()[0];
        if($forNameColor == NULL){
            continue;
        }       
        $nameColor = ucwords(strtolower($forNameColor));
        $arrColoreNameB[] = $nameColor;
        $linkColors = $pq->find('img')->attr('src');
        $arrColorLinkB[] = $linkColors;
        $nameColorForFile = str_replace(' ', '', $nameColor).'.png';
        $arrColoreNameFileB[] = $nameColorForFile;
        $pathToColor = $pathToColorFolder.'/'.$nameColorForFile;
        $imgColor = file_get_contents($linkColors);
        file_put_contents($pathToColor, $imgColor);
    }
   
    // delete dublicate & new indexes an arrays
    $arrColoreName = array_values(array_unique($arrColoreNameB));
    $arrColorLink = array_values(array_unique($arrColorLinkB));
    $arrColoreNameFile = array_values(array_unique($arrColoreNameFileB));
    
    // content complited for return and put to Excel
    $nameC = '';
    $linkC = '';
    $nameCFile = '';
    $c = 0;    
    foreach ($arrColoreName as $n) {
        if ($c == 0) {
            $nameC = $n;
            $linkC = $arrColorLink[$c];
            $nameCFile = $arrColoreNameFile[$c];
            $c++;
            continue;
        }
        $nameC = $nameC . PHP_EOL . $n;
        $linkC = $linkC . PHP_EOL . $arrColorLink[$c];
        $nameCFile = $nameCFile . PHP_EOL . $arrColoreNameFile[$c];
        $c++;
    }
    $arrColorsInPage = array(
        'names' => $nameC,
        'links' => $linkC,
        'namesF' => $nameCFile
    );
    
    return $arrColorsInPage;
}

function findImage($pqHTML, $pathToProduktFolder, $namePaver) {
    // array for names main images
    $arrImageName = array();
    $arrImageLink = array();
    $contArea = $pqHTML->find('.TabbedPanels .TabbedPanelsTabGroup');
    $imgPanel = $pqHTML->find('.TabbedPanels .TabbedPanelsContentGroup');

    // create Main image folder
    $pathToImageFolder = $pathToProduktFolder . '/' . 'Image';
    mkdir($pathToImageFolder);

    foreach ($contArea as $imgSel) {
        $pqImgSel = pq($imgSel);
        $li = $pqImgSel->find('.TabbedPanelsTab')->getString();

        // create & added ImageName
        foreach ($li as $nameSelect) {
            $nameImage = $namePaver . '-' . $nameSelect . '.png';
            $arrImageName[] = $nameImage;           
        }
    }

    foreach ($imgPanel as $p) {
        $panelPQ = pq($p);
        $imgAll = $panelPQ->find('img');
        $count = 0;
        foreach ($imgAll as $l) {
            $pq = pq($l);
            $link = $pq->attr('src');
            $arrImageLink[] = $link;
            $img = file_get_contents($link);
            $nameForImage = $arrImageName[$count];
            $pathToImage = $pathToImageFolder . '/' . $nameForImage;           
            file_put_contents($pathToImage, $img);
            $count++;
        }
    }
    $arrImageNameAndLink = array (
        'name' => $arrImageName,
        'link' => $arrImageLink
    );
    return $arrImageNameAndLink;
}

function getArrPage() {
    $arrPage = array(
        'id' => NULL,
        'Name wall' => NULL,        
        'Name image' => NULL,        
        'Description' => NULL,
        'Colors name' => NULL,
        'Name color in folder' =>NULL,        
        'Size' => NULL,
        'Number of patterns' => NULL,
        'Has guide' => NULL,
        'Links to color images' =>NULL,
        'Link image' =>NULL,
        'link page of paver' => NULL,
        'Literature link' => NULL
    );
    return $arrPage;
}

function getClipString($str,$strStart,$strEnd){
    $startPos = strpos($str, $strStart);
    $subStr1 = substr($str, $startPos);
    $endPos = (strpos($subStr1, $strEnd)) + 4;
    $subStr = substr($subStr1, 0, $endPos);
    return $subStr;    
}

function getIntFromString($string){
    $str = str_replace(' ', '', $string);
    $dumpStrInt = getClipString($str, ':', 'px');
    $strLen = strlen($dumpStrInt);
    $strInt = substr($dumpStrInt, 1, $strLen);
    $int = 0 + $strInt;
    return $int;
}

function getSizeAndName($pq, $count) {
    $size = $pq->next()->getString()[0];
    if ($count == 1) {
        $sizesComplited = $size;
    } else {
        $sizesComplited = $sizesComplited . PHP_EOL . $size;
    }
    $nameSizeF = $size . '.png';
    $nameSizeF1 = str_replace('/', '-', $nameSizeF);
    $nameSizeF2 = str_replace(' ', '', $nameSizeF1);
    $nameSizeF3 = str_replace('"\"', '-', $nameSizeF2);
    $nameSizeForFile = str_replace(':', '-', $nameSizeF3);
    $arrReturn = array(
        'sizesComplited' => $sizesComplited,
        'nameSizeForFile' => $nameSizeForFile
    );
    return $arrReturn;
}

function hexToRgb($color) {
    // проверяем наличие # в начале, если есть, то отрезаем ее
    if ($color[0] == '#') {
        $color = substr($color, 1);
    }   
    // разбираем строку на массив
    if (strlen($color) == 6) { // если hex цвет в полной форме - 6 символов
        list($red, $green, $blue) = array(
            $color[0] . $color[1],
            $color[2] . $color[3],
            $color[4] . $color[5]
        );
    } elseif (strlen($cvet) == 3) { // если hex цвет в сокращенной форме - 3 символа
        list($red, $green, $blue) = array(
            $color[0]. $color[0],
            $color[1]. $color[1],
            $color[2]. $color[2]
        );
    }else{
        return false; 
    }
 
    // переводим шестнадцатиричные числа в десятичные
    $red = hexdec($red); 
    $green = hexdec($green);
    $blue = hexdec($blue);
     
    // вернем результат
    return array(
        'red' => $red, 
        'green' => $green, 
        'blue' => $blue
    );
}

function writeToExcel($arrAll){
require_once ('Classes\PHPExcel.php');
$phpExcel = new PHPExcel();
$sheet = $phpExcel->getActiveSheet(); //активный лист
$arrayTitle = array_keys($arrAll[0]); //ключи массива в качестве заголовков
$arrayABC = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W');
$count    = 0;
// формируем заголовки со стилями
foreach ($arrayTitle as $title){
    $column = $arrayABC[$count];               
    $sheet->setCellValueExplicit($column.'1', $title, PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle($column.'1')
          ->getFill()
          ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
          ->getStartColor()
          ->setRGB('D3D3D3');
    $sheet->getStyle($column.'1')
           ->getAlignment()
           ->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($column.'1')
           ->getFont()
           ->setBold(TRUE);
    $count++;
}
// устанавливаем ширину колонок
$sheet->getColumnDimension('A')->setWidth(5); //id
$sheet->getColumnDimension('B')->setWidth(20);//Name paver
$sheet->getColumnDimension('C')->setWidth(40);//Name image
$sheet->getColumnDimension('D')->setWidth(70);//Description
$sheet->getColumnDimension('E')->setWidth(20);//Colors name
$sheet->getColumnDimension('F')->setWidth(20);//Name color in folder
$sheet->getColumnDimension('G')->setWidth(25);//Size
$sheet->getColumnDimension('H')->setWidth(20);//Number of patterns
$sheet->getColumnDimension('I')->setWidth(35);//Has guide
$sheet->getColumnDimension('J')->setWidth(50);//Links to color images
$sheet->getColumnDimension('K')->setWidth(50);//Link image
$sheet->getColumnDimension('L')->setWidth(50);//link page of paver
$sheet->getColumnDimension('M')->setWidth(50);//Literature link



$row = 2;
//заполнение основным контентом
foreach ($arrAll as $arrayBlock) { // проход ро блокам с вложенными массивами
        $count = 0;
        $sheet->getRowDimension($row)->setRowHeight(60); // высота строки
        // стили строки
        $sheet->getStyle($row)->getAlignment()->applyFromArray(
                array(
                    'wrap'       => TRUE,// перенос в несколько строк (используеться ниже .PHP_EOL)                   
                    'vertical'   => PHPExcel_Style_Alignment::VERTICAL_CENTER,// вертикальное центрирование
                )
        );  
        // проход по массиву с контентом
        foreach ($arrayBlock as $value) {
            $clmn = $arrayABC[$count]; // берем букву колонки, в зависимости от счетчика
            if (!is_array($value)) { // проверка на то, что едемент массива так же не является вложенным массивом
                $sheet->setCellValueExplicit($clmn . "$row", $value, PHPExcel_Cell_DataType::TYPE_STRING); //заполняем ячейку
                // устанавливаем стили для некоторых колонок
                if($clmn == "C"){ $sheet->getStyle("C"."$row")->getFont()->setSize(9);}
                if($clmn == "D"){ $sheet->getStyle("D"."$row")->getFont()->setSize(9);}
                if($clmn == "E"){ $sheet->getStyle("E"."$row")->getFont()->setSize(9);}
                if($clmn == "F"){ $sheet->getStyle("F"."$row")->getFont()->setSize(9);} 
                if($clmn == "G"){ $sheet->getStyle("G"."$row")->getFont()->setSize(9);}
                if($clmn == "H"){ $sheet->getStyle("H"."$row")->getFont()->setSize(9);
                 $sheet->getStyle("H".$row)->getAlignment()->applyFromArray(
                            array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER));
                }
                if($clmn == "I"){ $sheet->getStyle("I"."$row")->getFont()->setSize(8);
                    $sheet->getStyle("I".$row)->getAlignment()->applyFromArray(
                            array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER));
                }
                if($clmn == "J"){ $sheet->getStyle("J"."$row")->getFont()->setSize(8);}
                if($clmn == "K") {$sheet->getStyle("K" . "$row")->getFont()->setSize(8);}
                if($clmn == "L"){ $sheet->getStyle("L"."$row")->getFont()->setSize(8);}
                if($clmn == "M"){ $sheet->getStyle("M"."$row")->getFont()->setSize(8);}
                if($clmn == "B"){ $sheet->getStyle("B"."$row")->getFont()->setBold(TRUE);}
            } else { // если вложенный массив, проходимся и по нему 
                $t = "";
                foreach ($value as $v){
                    $t = $t.$v.PHP_EOL;
                } 
                $sheet->setCellValueExplicit($clmn . "$row", $t, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getStyle($clmn."$row")->getFont()->setSize(9);               
            }
            $count++;
        }
        $row++;
    }
    $objWriter = PHPExcel_IOFactory::createWriter($phpExcel, 'Excel2007');
    $fileName = 'CstpaversRetaningWalls.xlsx';
    $objWriter->save($fileName);
}

parser("http://www.cstpavers.com/");