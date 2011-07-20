<?php
/**
 * @file Pic.php
 * Функция выполняет изменение размера картинки и кэширует результат
 *
 * @since 20.07.2011
 *
 * @author blade39 <blade39@kolosstudio.ru>,
 * @version 1.1
 */

define('PIC_CACHE_SITE_ROOT',$_SERVER['DOCUMENT_ROOT']);
define('PIC_CACHE_PATH',$_SERVER['DOCUMENT_ROOT'].'/bitrix/cache/Pic');
define('PIC_CACHE_PATH_URL','/bitrix/cache/Pic');
define('PIC_CACHE_DEFAULT_IMAGE','');

function Pic($params)
{
	if($params['src']=='') return '';
	$attributes=array(
		'src',
		'mode',
		'default',
		'lifetime',
	);
	if(file_exists(PIC_CACHE_SITE_ROOT.$params['src']))
	{
		$sSizeFile='';
		if($params['width']!='') $sSizeFile.=intval($params['width']);
		$sSizeFile.='x';
		if($params['height']!='') $sSizeFile.=intval($params['height']);
		$cacheDir=PIC_CACHE_PATH.$params['src'].'/';
		$cacheFile=PIC_CACHE_PATH_URL.$params['src'].'/'.$sSizeFile.'.jpeg';
		$cachePath=PIC_CACHE_PATH.$params['src'].'/'.$sSizeFile.'.jpeg';
		if(!file_exists($cachePath))
		{
			//Такой файл не был закеширован, значит надо его создавать
			try
			{
				$obImage=new CImageResizer(PIC_CACHE_SITE_ROOT.$params['src']);
				$obMode=new CScale(intval($params['width']),intval($params['height']));
				if($params['mode']=='stretch')
					$obMode=new CRectGenerator(intval($params['width']),intval($params['height']));
				elseif($params['mode']=='crop')
					$obMode=new CCropToCenter(intval($params['width']),intval($params['height']));
				elseif($params['mode']=='croptop')
					$obMode=new CCropToTop(intval($params['width']),intval($params['height']));
				if($obImage->Resize($obMode))
				{
					if(!file_exists($cacheDir))
						if(!@mkdir($cacheDir,0755,true)) return '';
					if($obImage->Save($cachePath))
					{
						chmod($cachePath,0655);
					}
					else
					{
						throw new Exception('SYSTEM_CANT_SAVE');
					}
				}
				else
				{
					throw new Exception('SYSTEM_CANT_RESIZE');
				}
			}
			catch (Exception $e)
			{
				$cacheFile=$params['src'];
			}
		}
	}
	elseif($params['default']!='')
	{
		$cacheFile=$params['default'];
	}
	elseif(PIC_CACHE_DEFAULT_IMAGE!='')
	{
		$cacheFile=PIC_CACHE_DEFAULT_IMAGE;
	}
	else
	{
		return '';
	}
	$res='<img src="'.$cacheFile.'"';
	foreach($params as $key=>$value)
	{
		if($params['keepSmall']=='Y' && ($key=='width' || $key=='height')) continue;
		if(!in_array($key,$attributes))
			$res.=' '.$key.'="'.$value.'"';
	}
	$res.='/>';
	return $res;
}

/**
 * Класс работы с изображениями v2.6
 * Изменение изображений по след. параметрам: Ширина, Высота, Пропорциональность, Белые поля.
 *
 * Автор: Егор Болгов
 */

class CImageResizer
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
			throw new Exception('SYSTEM_NOT_A_FILE');

		$info = pathinfo($inputfile); // Информация о файле
		list($width, $height, $type, $attr) = getimagesize($this->sFilename);

		$this->iWidth = $width;
		$this->iHeight = $height;
		$this->iType = $type;
		$this->obRectangle=false;

		if($this->iWidth*$this->iHeight*4>(CImageResizer::GetMaxMemory()-1024*1024))
		{
			throw new Exception(SYSTEM_NO_MEMORY,1,($this->width_orig*$this->height_orig*4).'/'.(CImageResizer::GetMaxMemory()-1024*1024));
		}
	}

	/**
	 * Метод определяет максимальный доступный объём памяти для обработки изображения
	 */
	static function GetMaxMemory()
	{
		$val=ini_get('memory_limit');
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		switch($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return $val;
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
				throw new Exception('WH by zero');
			$this->obRectangle=new CRectGenerator($this->iWidth,$this->iHeight,$image_w,$image_h);
		}
		$this->obRectangle->SetSourceSize($this->iWidth,$this->iHeight);
		if($arCoord=$this->obRectangle->GetCoord())
		{
			switch ($this->iType)
			{
				case 2: $im = imagecreatefromjpeg($this->sFilename);  break;
				case 3: $im = imagecreatefrompng($this->sFilename); break;
				default:  throw new Exception('PHOTOGALLERY_WRONG_FILE', E_USER_WARNING);  break;
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
	}

	/**
	 * Метод выполняет сохранение нового изображения по новому пути
	 */
	function Save($path,$quality=98)
	{
		if($this->newImage)
		{
			$res=@imagejpeg($this->newImage,$path,$quality);
			if(!imagedestroy($this->newImage)) throw new Exception('SYSTEM_STRANGE_ERROR');
			$this->newImage=0;
			return $res;
		}
		return false;
	}
}


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

