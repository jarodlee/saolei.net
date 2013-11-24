<?php
class Logger
{
    const LOG_LEVEL_NONE    = 0x00;
    const LOG_LEVEL_FATAL   = 0x01;
    const LOG_LEVEL_WARNING = 0x02;
    const LOG_LEVEL_NOTICE  = 0x04;
    const LOG_LEVEL_TRACE   = 0x08;
    const LOG_LEVEL_DEBUG   = 0x10;
    const LOG_LEVEL_ALL     = 0xFF;

    public static $arrLogLevels = array(
        self::LOG_LEVEL_NONE    => 'NONE',
        self::LOG_LEVEL_FATAL   => 'FATAL',
        self::LOG_LEVEL_WARNING => 'WARNING',
        self::LOG_LEVEL_NOTICE  => 'NOTICE',
        self::LOG_LEVEL_TRACE    => 'TRACE',
        self::LOG_LEVEL_DEBUG   => 'DEBUG',
        self::LOG_LEVEL_ALL     => 'ALL',
    );

    protected $intLevel;
    protected $strLogFile;
    protected $arrSelfLogFiles;
    protected $intLogId;
    protected $intMaxFileSize;

    private static $comParaArray = array();
    private static $instance = null;
    private static $log_version = null;
    private static $log_type = null;

    private function __construct($arrLogConfig)
    {
        $this->intLevel         = intval($arrLogConfig['intLevel']);
        $this->strLogFile        = $arrLogConfig['strLogFile'];
        $this->arrSelfLogFiles  = $arrLogConfig['arrSelfLogFiles'];
        // use framework logid as default
        $this->intLogId            = 0;
        $this->intMaxFileSize  = $arrLogConfig['intMaxFileSize'];

    }

    public static function getInstance()
    {
        if( self::$instance === null )
        {
            self::$instance = new Logger($GLOBALS['LOG']);
        }

        return self::$instance;
    }


    public function writeLog($intLevel, $str, $errno = 0, $arrArgs = null, $depth = 0)
    {
        $comParaArray = self::$comParaArray;
        if($arrArgs===null)
        {
            $arrArgs = $comParaArray;
        }
        else
        {
            if( is_array($arrArgs))
            {
            	$arrArgs = array_merge($comParaArray,$arrArgs);
            }
        }

        if( !($this->intLevel & $intLevel) || !isset(self::$arrLogLevels[$intLevel]) )
        {
            return;
        }

        $strLevel = self::$arrLogLevels[$intLevel];

        $strLogFile = $this->strLogFile;
        if( ($intLevel & self::LOG_LEVEL_WARNING) || ($intLevel & self::LOG_LEVEL_FATAL) )
        {
            $strLogFile .= '.wf';
        }

        $trace = debug_backtrace();
        if( $depth >= count($trace) )
        {
            $depth = count($trace) - 1;
        }

        $file = isset($trace[$depth]['file'])?basename($trace[$depth]['file']):'';
        $line = isset($trace[$depth]['line'])?$trace[$depth]['line']:'';
        //$file = basename($trace[$depth]['file']);
        //$line = $trace[$depth]['line'];

        $strArgs = '';
        if( is_array($arrArgs) && count($arrArgs) > 0 )
        {
            foreach( $arrArgs as $key => $value )
            {
            	$strArgs .= "{$key}[$value] ";
            }
        }

        $str = sprintf( "%s %s %s: %s [%s:%d] errno[%d] ip[%s] logId[%u] uri[%s] %s%s\n",
            self::$log_version,
            self::$log_type,
            $strLevel,
            date('m-d H:i:s:', time()),
            $file, $line, $errno,
            self::getClientIP(),
            $this->intLogId,
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            $strArgs, $str);

        if($this->intMaxFileSize > 0)
        {
            clearstatcache();
            $arrFileStats = stat($strLogFile);
            if( is_array($arrFileStats) && floatval($arrFileStats['size']) > $this->intMaxFileSize )
            {
            	unlink($strLogFile);
            }
        }

        return self::flushLog($strLogFile, $str, FILE_APPEND);
    }

    public function writeSelfLog($strKey, $str, $arrArgs = null)
    {
        $comParaArray = self::$comParaArray;
        if($arrArgs===null)
        {
            $arrArgs = $comParaArray;
        }
        else
        {
            if( is_array($arrArgs))
            {
            	$arrArgs = array_merge($comParaArray,$arrArgs);
            }
        }


        if( isset($this->arrSelfLogFiles[$strKey]) )
        {
            $strLogFile = $this->arrSelfLogFiles[$strKey];
        }
        else
        {
            return;
        }

        $strArgs = '';
        if( is_array($arrArgs) && count($arrArgs) > 0 )
        {
            foreach( $arrArgs as $key => $value )
            {
            	$strArgs .= "{$key}[$value] ";
            }
        }

        $str = sprintf( "%s: %s ip[%s] logId[%u] uri[%s] %s%s\n",
            $strKey,
            date('m-d H:i:s:', time()),
            self::getClientIP(),
            $this->intLogId,
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            $strArgs, $str);

        if($this->intMaxFileSize > 0)
        {
            clearstatcache();
            $arrFileStats = stat($strLogFile);
            if( is_array($arrFileStats) && floatval($arrFileStats['size']) > $this->intMaxFileSize )
            {
            	unlink($strLogFile);
            }
        }
        return self::flushLog($strLogFile, $str, FILE_APPEND);
    }

    public static function setComPara($aComParaArray,$log_version='1.0',$log_type='peso')
    {
        $log = Logger::getInstance();
        $log->setSelfPara($aComParaArray,$log_version,$log_type);
    }

    private function setSelfPara($aComParaArray,$log_version,$log_type)
    {
        self::$comParaArray = $aComParaArray;
        self::$log_version = $log_version;
        self::$log_type = $log_type;
    }

    public static function selflog($strKey, $str, $arrArgs = null)
    {
        $log = Logger::getInstance();
        return $log->writeSelfLog($strKey, $str, $arrArgs);
    }

    public static function debug($str, $errno = 0, $arrArgs = null, $depth = 0)
    {
        $log = Logger::getInstance();
        return $log->writeLog(self::LOG_LEVEL_DEBUG, $str, $errno, $arrArgs, $depth + 1);
    }

    public static function trace($str, $errno = 0, $arrArgs = null, $depth = 0)
    {
        $log = Logger::getInstance();
        return $log->writeLog(self::LOG_LEVEL_TRACE, $str, $errno, $arrArgs, $depth + 1);
    }

    public static function notice($str, $errno = 0, $arrArgs = null, $depth = 0)
    {
        $log = Logger::getInstance();
        return $log->writeLog(self::LOG_LEVEL_NOTICE, $str, $errno, $arrArgs, $depth + 1);
    }

    public static function warning($str, $errno = 0, $arrArgs = null, $depth = 0)
    {
        $log = Logger::getInstance();
        return $log->writeLog(self::LOG_LEVEL_WARNING, $str, $errno, $arrArgs, $depth + 1);
    }

    public static function fatal($str, $errno = 0, $arrArgs = null, $depth = 0)
    {
        $log = Logger::getInstance();
        return $log->writeLog(self::LOG_LEVEL_FATAL, $str, $errno, $arrArgs, $depth + 1);
    }

    public static function setLogId($intLogId)
    {
        Logger::getInstance()->intLogId = $intLogId;
    }

    public static function getClientIP()
    {
        if( isset($_SERVER['HTTP_X_FORWARDED_FOR']) )
        {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        elseif( isset($_SERVER['HTTP_CLIENTIP']) )
        {
            $ip = $_SERVER['HTTP_CLIENTIP'];
        }
        elseif( isset($_SERVER['REMOTE_ADDR']) )
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        elseif( getenv('HTTP_X_FORWARDED_FOR') )
        {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif( getenv('HTTP_CLIENTIP') )
        {
            $ip = getenv('HTTP_CLIENTIP');
        }
        elseif( getenv('REMOTE_ADDR') )
        {
            $ip = getenv('REMOTE_ADDR');
        }
        else
        {
            $ip = '127.0.0.1';
        }

        $pos = strpos($ip, ',');
        if( $pos > 0 )
        {
            $ip = substr($ip, 0, $pos);
        }

        return trim($ip);
    }
    protected function flushLog($strLogFile, $str, $flags){

        //if(!empty($_SERVER['HTTP_HOST'])&&(stripos($_SERVER['HTTP_HOST'],'192.168')===false)){
            //echo $str;
        //}else{
            file_put_contents($strLogFile, $str, $flags);
        //}
    }
}
