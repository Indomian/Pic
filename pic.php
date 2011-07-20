<?php
/**
 * @file Pic.php
 * Функция выполняет изменение размера картинки и кэширует результат
 *
 * @since 20.07.2011
 *
 * @author blade39 <blade39@kolosstudio.ru>
 * @version 1.1
 */

define('PIC_CACHE_PATH',$_SERVER['DOCUMENT_ROOT'].'/bitrix/cache/Pic');

function Pic($params)
{
	if($params['src']=='') return '';
	$sSizeFile='';
	if($params['width']!='') $sSizeFile.=intval($params['width']);
	$sSizeFile.='x';
	if($params['height']!='') $sSizeFile.=intval($params['height']);
	$cacheDir=PIC_CACHE_PATH.$params['src'].'/';
	if(!file_exists($cacheDir))
		@mkdir(
	$cacheFile='/uploads/PicCache'.$params['src'].'/'.$sSizeFile.'.jpeg';
	$attributes=array(
		'src',
		'mode',
		'default',
		'lifetime',
	);
	/**
	 * @todo Убрать эту хрень к чертям собачьим
	 */
	/*if(!isset($params['cache_time'])||intval($params['cache_time'])<=0)
	{
		$obConfig=new CConfigParser('main');
		$ks_config=$obConfig->LoadConfig();
		$params['lifetime'] = $ks_config['lifetime'];
	}*/
	try
	{
		if(file_exists(ROOT_DIR.$cacheFile))// && (filectime(ROOT_DIR.$cacheFile)+$params['lifetime'] >= time()))
		{
			$res='<img src="'.$cacheFile.'"';
			foreach($params as $key=>$value)
			{
				if(!in_array($key,$attributes))
				{
					$res.=' '.$key.'="'.$value.'"';
				}
			}
			$res.='/>';
			return $res;
		}
		else
		{
			//Такой файл не был закеширован, значит надо его создавать
			if(file_exists(ROOT_DIR.$params['src']))
			{
				include_once(MODULES_DIR.'/main/libs/class.ImageResizer.php');
				$obImage=new ImageResizer($params['src']);
				$obImage->isCreateDir=false;
				$obImage->isSave=false;
				$bKeepRatio=false;
				$bKeepRatioWb=true;
				if($params['mode']=='stretch')
				{
					$bKeepRatio=false;
					$bKeepRatioWb=false;
				}
				elseif($params['mode']=='crop')
				{
					$bKeepRatio=true;
					$bKeepRatioWb=true;
				}
				elseif($params['mode']=='resize')
				{
					$bKeepRatio=true;
					$bKeepRatioWb=false;
				}
				$obImage->Resize(intval($params['width']),intval($params['height']),$bKeepRatio,$bKeepRatioWb,false);
				if(!file_exists($cacheDir))
				{
					$KS_FS->makedir($cacheDir);
				}
				if(!$obImage->Save(ROOT_DIR.$cacheFile))
				{
					throw new CError('SYSTEM_FILE_NOT_FOUND_OR_NOT_WRITABLE',$cacheFile);
				}
				chmod(ROOT_DIR.$cacheFile,0655);
				$res='<img src="'.$cacheFile.'"';
				foreach($params as $key=>$value)
				{
					if(!in_array($key,$attributes))
					{
						$res.=' '.$key.'="'.$value.'"';
					}
				}
				$res.='/>';
				return $res;
			}
			elseif($params['default']!='')
			{
				$res='<img src="'.$params['default'].'"';
				foreach($params as $key=>$value)
				{
					if(!in_array($key,$attributes))
					{
						$res.=' '.$key.'="'.$value.'"';
					}
				}
				$res.='/>';
				return $res;
			}
			throw new CError('SYSTEM_FILE_NOT_FOUND',0,$params['src']);
		}
	}
	catch(CError $e)
	{
		return $e->__toString();
	}
}

function widget_params_Pic($params)
{

}
?>
<?php

include_once MODULES_DIR.'/photogallery/libs/class.CRectGenerator.php';

/**
 * Класс работы с изображениями v2.6
 * Изменение изображений по след. параметрам: Ширина, Высота, Пропорциональность, Белые поля.
 *
 * Автор: Егор Болгов
 */

class CImageResizer extends CBaseObject
{
	protected $sFilename;
	protected $iWidth;
	protected $iHeight;
	protected $iType;
	protected $obRectangle;
  	/**
  	 * Переменная для хранения нового изображения после ресайза
  	 */
  	protected $newImage;
  	/**
  	 * Статический массив допустимых расширений
  	 */
  	static $arAllowExt=array('jpg','jpeg','png');

	/**
	 * Конструктор, принимает только путь к файлу, абсолютный на сервере
	 */
	function __construct($inputfile)
	{
		$this->sFilename = $inputfile;
		if(!is_file($inputfile))
			throw new CError('SYSTEM_NOT_A_FILE');

		$info = pathinfo($inputfile); // Информация о файле
		list($width, $height, $type, $attr) = getimagesize($this->sFilename);

		$this->iWidth = $width;
		$this->iHeight = $height;
		$this->iType = $type;
		$this->obRectangle=false;

		if($this->iWidth*$this->iHeight*4>(GetMaxMemory()-1024*1024))
		{
			throw new CError(SYSTEM_NO_MEMORY,1,($this->width_orig*$this->height_orig*4).'/'.(GetMaxMemory()-1024*1024));
		}
	}

	/**
	 * Деструктор выполняет автоматическое удаление изображения
	 */
	function __destruct()
	{
		if($this->newImage)
		{
			imagedestroy($this->newImage);
			$this->newImage=0;
		}
	}

	/**
	 *  Метод изменения размера изображения
	 * @param $image_w - требуемая ширина изображения
	 * @param $image_h - требуемая высота изображения
	 */
	function Resize($image_w,$image_h=false)
	{
		if(is_object($image_w) && $image_w instanceof CRectGenerator)
		{
			$this->obRectangle=$image_w;
		}
		else
		{
			if($image_w == 0 && $image_h == 0)
				throw new CError('WH by zero');
			$this->obRectangle=new CRectGenerator($this->iWidth,$this->iHeight,$image_w,$image_h);
		}
		$this->obRectangle->SetSourceSize($this->iWidth,$this->iHeight);
		if($arCoord=$this->obRectangle->GetCoord())
		{
			switch ($this->iType)
			{
				case 2: $im = imagecreatefromjpeg($this->sFilename);  break;
				case 3: $im = imagecreatefrompng($this->sFilename); break;
				default:  throw new CError('PHOTOGALLERY_WRONG_FILE', E_USER_WARNING);  break;
			}
			$newImg = imagecreatetruecolor($arCoord['w1'], $arCoord['h1']);
			if(imagecopyresampled($newImg, $im, $arCoord['x1'], $arCoord['y1'], $arCoord['x'], $arCoord['y'], $arCoord['w1'],$arCoord['h1'], $arCoord['w'], $arCoord['h']))
			{
				$this->newImage=$newImg;
				imagedestroy($im);
				return true;
			}
			imagedestroy($im);
		}
		return false;

		if($image_ratio_wb == true)
		{
			$wb_type = 0;

			if($this->width_orig > $this->height_orig || $this->width_orig == $this->height_orig)
			{
				$aspect_ratio = (float) $this->height_orig / $this->width_orig;
				$image_h_r = round($image_w * $aspect_ratio);
				$image_w_r = $image_w;
				$wb_type = 1;
				if($image_h < $image_h_r)
				{
					$aspect_ratio = (float) $this->width_orig / $this->height_orig;
					$image_w_r = round($image_h * $aspect_ratio);
					$image_h_r = $image_h;
					$wb_type = 2;
				}
			}
			elseif($this->width_orig < $this->height_orig)
			{
				$aspect_ratio = (float) $this->width_orig / $this->height_orig;
				$image_w_r = round($image_h * $aspect_ratio);
				$image_h_r = $image_h;
				$wb_type = 2;
				if($image_w < $image_w_r)
				{
					$aspect_ratio = (float) $this->height_orig / $this->width_orig;
					$image_h_r = round($image_w * $aspect_ratio);
					$image_w_r = $image_w;
					$wb_type = 1;
				}
			}
			$newImg_first = imagecreatetruecolor($image_w_r, $image_h_r);
			imagecopyresampled($newImg_first, $im, 0, 0, 0, 0, $image_w_r, $image_h_r, $this->width_orig, $this->height_orig);


			$newImg = imagecreatetruecolor($image_w, $image_h);
			if($this->border_color != "")
			{
				$colors = explode(" ",$this->border_color);
				$r = $colors[0];
				$g = $colors[1];
				$b = $colors[2];
			} else { $r = 255; $g = 255; $b = 255; }

			$background_color = imagecolorallocate($newImg, $r, $g, $b);
			imagefill($newImg, 0, 0, $background_color);

			if($wb_type == 1)
			{
				$one = round(($image_h - $image_h_r)/2);

				imagecopyresampled($newImg, $newImg_first, 0, round(($image_h - $image_h_r)/2), 0, 0, $image_w_r, $image_h_r, $image_w_r, $image_h_r);
			}

			if($wb_type == 2)
			{
				imagecopyresampled($newImg, $newImg_first,  round(($image_w - $image_w_r)/2), 0, 0, 0, $image_w_r, $image_h_r, $image_w_r, $image_h_r);
			}

		}
	}

	/**
	 * Метод выполняет сохранение нового изображения по новому пути
	 */
	function Save($path,$quality=98)
	{
		if($this->newImage)
		{
			$res=@imagejpeg($this->newImage,$path,$quality);
			if(!imagedestroy($this->newImage)) throw new CError('SYSTEM_STRANGE_ERROR');
			$this->newImage=0;
			return $res;
		}
		return false;
	}
}

<?php

if( !defined('KS_ENGINE') ) {die("Hacking attempt!");}

/**
 * Класс выполняет генерацию координат для ресайза картинки
 */
class CRectGenerator
{
	protected $arSource;
	protected $arResult;

	function __construct($arSource,$arResult,$rWidth=false,$rHeight=false)
	{
		if(is_array($arSource) && is_array($arResult))
		{
			$this->arSource=$arSource;
			$this->arResult=$arResult;
		}
		elseif($rWidth===false && $rHeight===false)
		{
			$this->arResult=array('width'=>$arSource,'height'=>$arResult);
			$this->arSource=false;
		}
		else
		{
			$this->arSource=array('width'=>$arSource,'height'=>$arResult);
			$this->arResult=array('width'=>$rWidth,'height'=>$rHeight);
		}
	}

	function SetSourceSize($w,$h)
	{
		$this->arSource=array('width'=>$w,'height'=>$h);
	}

	/**
	 * Метод возвращает координаты по простому способу
	 */
	function GetCoord()
	{
		if(!$this->arSource) return false;
		$arResult=array(
			'x'=>0,
			'y'=>0,
			'w'=>$this->arSource['width'],
			'h'=>$this->arSource['height'],
			'x1'=>0,
			'y1'=>0,
			'w1'=>$this->arResult['width'],
			'h1'=>$this->arResult['height']
		);
		return $arResult;
	}
}

class CScale extends CRectGenerator
{
	/**
	 * Метод возвращает координаты для усечения по центру
	 */
	function GetCoord()
	{
		$arResult=parent::GetCoord();
		if($arResult['w1']==0 && $arResult['h1']==0) return false;
		$fProp=$this->arSource['width']/$this->arSource['height'];
		if($arResult['h1']==0)
		{
			$arResult['h1']=$arResult['w1']/$fProp;
		}
		elseif($arResult['w1']==0)
		{
			$arResult['w1']=$arResult['h1']*$fProp;
		}
		if($arResult['w1']>$this->arSource['width'] || $arResult['h1']>$this->arSource['height'])
		{
			$arResult['w1']=$this->arSource['width'];
			$arResult['h1']=$this->arSource['height'];
		}
		return $arResult;
	}
}

class CCropToCenter extends CRectGenerator
{
	/**
	 * Метод возвращает координаты для усечения по центру
	 */
	function GetCoord()
	{
		$arResult=parent::GetCoord();
		//Считаем пропорции оригинала и результата
		$fProp=$this->arSource['width']/$this->arSource['height'];
		$fRProp=$this->arResult['width']/$this->arResult['height'];
		if($fRProp>$fProp)
		{
			//Если пропорции результата больше (т.е. ширина важнее)
			$scale=$this->arResult['width']/$this->arSource['width'];
			$iScaledHeight = round($this->arSource['height']*$scale);
			if($iScaledHeight>$this->arResult['height'])
			{
				//Высота ресайза больше высоты результата
				$arResult['y']=round(($iScaledHeight-$this->arResult['height'])/2/$scale);
				$arResult['h']=round($this->arResult['height']/$scale);
			}
		}
		else
		{
			//Пропорции исходного больше, значит важнее высота
			$scale=$this->arResult['height']/$this->arSource['height'];
			$iScaledWidth = round($this->arSource['width']*$scale);
			if($iScaledWidth>$this->arResult['width'])
			{
				//Если ширина картинки оказалась больше чем допустимая ширина
				//То надо посчитать смещение и изменить выводимую ширину
				$arResult['x']=round(($iScaledWidth-$this->arResult['width'])/2/$scale);
				$arResult['w']=round($this->arResult['width']/$scale);
			}
		}
		return $arResult;
	}
}

class CCropToTop extends CRectGenerator
{
	/**
	 * Метод возвращает координаты для усечения по центру или по верхнему краю
	 */
	function GetCoord()
	{
		$arResult=parent::GetCoord();
		//Считаем пропорции оригинала и результата
		$fProp=$this->arSource['width']/$this->arSource['height'];
		$fRProp=$this->arResult['width']/$this->arResult['height'];
		if($fRProp>$fProp)
		{
			//Если пропорции результата больше (т.е. ширина важнее)
			$scale=$this->arResult['width']/$this->arSource['width'];
			$iScaledHeight = round($this->arSource['height']*$scale);
			if($iScaledHeight>$this->arResult['height'])
			{
				//Высота ресайза больше высоты результата
				$arResult['y']=0;
				$arResult['h']=round($this->arResult['height']/$scale);
			}
		}
		else
		{
			//Пропорции исходного больше, значит важнее высота
			$scale=$this->arResult['height']/$this->arSource['height'];
			$iScaledWidth = round($this->arSource['width']*$scale);
			if($iScaledWidth>$this->arResult['width'])
			{
				//Если ширина картинки оказалась больше чем допустимая ширина
				//То надо посчитать смещение и изменить выводимую ширину
				$arResult['x']=round(($iScaledWidth-$this->arResult['width'])/2/$scale);
				$arResult['w']=round($this->arResult['width']/$scale);
			}
		}
		return $arResult;
	}
}

