<?php
/*    
	This file is part of STFC.
	Copyright 2006-2007 by Michael Krauss (info@stfc2.de) and Tobias Gafner
		
	STFC is based on STGC,
	Copyright 2003-2007 by Florian Brede (florian_brede@hotmail.com) and Philipp Schmidt
	
    STFC is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    STFC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class simpleGraph
{
    var $image;
    var $im;
    var $target;

    function create($imgWidth, $imgHeight){
        //header ("Content-type: image/png");
        $this->im = @imagecreatetruecolor ($imgWidth, $imgHeight) 
             or die ("Kann keinen neuen GD-Bild-Stream erzeugen");
             
        $background_color = ImageColorAllocate ($this->im, 240, 240, 240);
        imagefilledrectangle ($this->im, 0, 0, $imgWidth, $imgHeight, $background_color); 
    
        $text_color = ImageColorAllocate ($this->im, 0, 0, 0);
    }


  function headline($text)
  {
    $text_color = ImageColorAllocate ($this->im, 0, 0, 0);
    ImageString ($this->im, 3, 5, 5, $text, $text_color); 
  }
  
  function circle($arr)
  {
    $pos = 100;
      // choose a color for the ellipse
    $col_ellipse = imagecolorallocate($this->im, 200, 200, 200);
    $col_ellipse_top = imagecolorallocate($this->im, 180, 180, 180); 
        
    // draw the ellipse
    imagefilledellipse($this->im, 150, $pos+10 , 200, 100, $col_ellipse);
    imagefilledellipse($this->im, 150, $pos, 200, 100, $col_ellipse_top);
        
      $arrCount = count($arr); 
      $start = 0;
      $gesamt = 0;
      $textline = 20;
      for($x=0; $arrCount>$x; $x++){
          $move = $arr[$x]['size'] * 3.6 + $gesamt;
          $add = $arr[$x]['size'] * 3.6 ;

          $fillcolor = ImageColorAllocate ($this->im, rand(0, 255), rand(0, 255), rand(0, 255)); 
          $black = ImageColorAllocate ($this->im, 0, 0, 0);
          // Startpunkte / mitte des Kuchens
        $point[] = 150;
        $point[] = $pos;
            
        $radius =100;
             
        for ($i=$start;$i<=$move;$i++)
        {
           $point[] = 150 + ($radius  * sin(deg2rad($i)));
           $point[] = $pos - ($radius * cos(deg2rad($i))*0.5);   
        }
        $start = $i; 
        
        ImageFilledPolygon ($this->im, $point, (sizeof($point)/2), $fillcolor);
        unset($point);
        
        $textline = $textline + 15;
        imagefilledrectangle ($this->im, 300-15, $textline+2, 300-5, $textline+13, $fillcolor); 
        imagerectangle ($this->im, 300-5, $textline+2, 300-15, $textline+13, $black);
        
        ImageString ($this->im, 2, 300, $textline, $arr[$x]['name'], $text_color);
        
        $gesamt = $gesamt+$add; 
        
    }
      
  }
  
  function bar($arr)
  {
    $gesamtLaenge = 790;
    $line_color = imagecolorallocate($this->im, 130, 130, 130);
    $red_line = imagecolorallocate($this->im, 200, 0, 0); 
    $rect_color = imagecolorallocate($this->im, 130, 130, 130);
    $rect_color_shaddow = imagecolorallocate($this->im, 50, 50, 50);
      imageline($this->im, 30, 30, 30, 180, $line_color);
      imageline($this->im, $gesamtLaenge, 170, 20, 170, $line_color); 
      

      
    $arrCount = count($arr);
    $biggestValue = 0;
    for($i=0; $i<$arrCount; $i++){
        if($arr[$i]['size'] > $biggestValue){
            $biggestValue = $arr[$i]['size']; 
            $biggestArr = $i; 
        }
    }
    

    imageline($this->im, $gesamtLaenge, 50, 20, 50, $red_line);
    imageline($this->im, 32, 50, 20, 50, $line_color);
    ImageString ($this->im, 1, 5, 40, $biggestValue, $text_color); 

    imageline($this->im, $gesamtLaenge, 110, 20, 110, $red_line);
    imageline($this->im, 32, 110, 20, 110, $line_color);
    ImageString ($this->im, 1, 5, 100, round($biggestValue/2), $text_color); 
    
    $start_x = 35;
    $maxhoehe = 120;
    if($biggestValue){
        $faktor1 = $maxhoehe / $biggestValue;
    } 
    
    for($i=0; $i<$arrCount; $i++){
        $hoehe = $faktor1 * $arr[$i]['size']; 
        $hoehe = ($maxhoehe + 50) - $hoehe;
        if($hoehe < ($maxhoehe + 50)-5)imagefilledrectangle ($this->im, $start_x+5, $hoehe+5, $start_x+20+5, 170, $rect_color_shaddow); // Shaddow
        imagefilledrectangle ($this->im, $start_x, $hoehe, $start_x+20, 170, $rect_color); 
        ImageString ($this->im, 1, $start_x, 180, $arr[$i]['name'], $text_color);
        ImageString ($this->im, 1, $start_x, $hoehe-10, $arr[$i]['size'], $text_color);
        $start_x = $start_x + 30;
    }
  }
  
  function line($arr)
  {
  	$gesamtLaenge = 790;
    $line_color = imagecolorallocate($this->im, 130, 130, 130);
    $red_line = imagecolorallocate($this->im, 200, 0, 0);
    $rect_color = imagecolorallocate($this->im, 130, 130, 130);
    $rect_color_shaddow = imagecolorallocate($this->im, 50, 50, 50);
    imageline($this->im, 30, 30, 30, 180, $line_color);
    imageline($this->im, $gesamtLaenge, 170, 20, 170, $line_color); 
      

      
    $arrCount = count($arr);
    $biggestValue = 0;
    for($i=0; $i<$arrCount; $i++){
        if($arr[$i]['size'] > $biggestValue){
            $biggestValue = $arr[$i]['size']; 
            $biggestArr = $i; 
        }
    }
    
  
    imageline($this->im, $gesamtLaenge, 50, 20, 50, $red_line);
    imageline($this->im, 32, 50, 20, 50, $line_color);
    ImageString ($this->im, 1, 5, 40, $biggestValue, $text_color); 

    imageline($this->im, $gesamtLaenge, 110, 20, 110, $red_line);
    imageline($this->im, 32, 110, 20, 110, $line_color);
    ImageString ($this->im, 1, 5, 100, round($biggestValue/2), $text_color); 
    
    $start_x = 35;
    $maxhoehe = 120;
    if($biggestValue){
        $faktor1 = $maxhoehe / $biggestValue;
    } 
    
    for($i=0; $i<$arrCount; $i++){
        $hoehe = $faktor1 * $arr[$i]['size']; 
        $hoehe = ($maxhoehe + 50) - $hoehe;
        
                
        $point[$i]["x"] = $start_x+30; //x
        $point[$i]["y"] = $hoehe; //y
        
        ImageString ($this->im, 1, $start_x, 180, $arr[$i]['name'], $text_color);
        ImageString ($this->im, 1, $start_x, $hoehe-10, $arr[$i]['size'], $text_color);
        $start_x = $start_x + 50;
    }
    
    $last_x = $point[0]["x"];
	$last_y = $point[0]["y"];
	
	imagesetthickness($this->im, 2);
    for ($i=0;$i<count($point);$i++)
    {
    	imageline($this->im, $last_x, $last_y, $point[$i]["x"], $point[$i]["y"], $line_color);
        $last_x = $point[$i]["x"];
		$last_y = $point[$i]["y"];
    }
  }

  function showGraph($target){
          $this->image = ImagePNG ($this->im, $target);
          return $this->image; 
     }
}



for($i=0; $i<4; $i++){ 
    $arr[$i]['size'] = 20+$i*3;
    $arr[$i]['name'] = 20+$i*3 ."% Graph";
}
?> 
