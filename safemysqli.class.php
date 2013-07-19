<?php
class SafeMySQLi
{

	private $conn;
	private $stats;
	private $emode;
	private $exname;

	protected $defaults = array(
		'host'      => 'localhost',
		'user'      => 'root',
		'pass'      => '',
		'db'        => 'test',
		'port'      => NULL,
		'socket'    => NULL,
		'pconnect'  => FALSE,
		'charset'   => 'utf8',
		'exception' => 'Exception', //Exception class name
	);

	const RESULT_ASSOC   = MYSQLI_ASSOC;
	const RESULT_NUM     = MYSQLI_NUM;

	function __construct($opt = array())
	{
		$opt = array_merge($this->defaults,$opt);

		$this->exname = $opt['exception'];

		if ($opt['pconnect'])
		{
			$opt['host'] = "p:".$opt['host'];
		}

		@$this->conn = mysqli_connect($opt['host'], $opt['user'], $opt['pass'], $opt['db'], $opt['port'], $opt['socket']);
		if ( mysqli_connect_errno() )
		{
			$this->error(mysqli_connect_errno()." ".mysqli_connect_error());
		}

		mysqli_set_charset($this->conn, $opt['charset']) or $this->error(mysqli_error($this->conn));
		unset($opt); // I am paranoid
	}

	function prepare($query, $args = array() )
	{
		$pattern = '~(\?[nsiuap]?|[nsiuap]?:[a-zA-Z_][a-zA-Z0-9_]*)~u';
		$array   = preg_split($pattern, $query , null, PREG_SPLIT_DELIM_CAPTURE);
		$out     = '';

		if (key($args) === 0)
		{
			$mode = 'numeric';
			
			$anum  = count($args);
			$pnum  = floor(count($array) / 2);
			if ( $pnum != $anum )
			{
				$this->error("Number of args ($anum) doesn't match number of placeholders ($pnum) in [$query]");
			}

		} else {

			$mode = 'named';
			
			$names = array();
		}

		foreach ($array as $i => $part)
		{
			if ( ($i % 2) == 0 )
			{
				$out .= $part;
				continue;
			}

            if ($part[0] == '?')
            {
				if ($mode == 'named')
				{
					$this->error("Cannot mix named and positional placeholders");
				}
                $value = array_shift($args);
                $type  = trim($part,"?");
                
            } else {
                
				if ($mode == 'numeric')
				{
					$this->error("Cannot mix named and positional placeholders");
				}
				
                list($type, $key) = explode(":", $part);
				
				if (isset($args[$key]))
				{
					$value = $args[$key];
					
				} elseif (isset($args[":$key"])) {
					
					$value = $args[":$key"];

				} else {

					$this->error("No key found for the named placeholder [$key] in the data array");
				}
            }
            
			switch ($type)
			{
				case '':
				case 's':
					$part = $this->escapeString($value);
					break;
				case 'n':
					$part = $this->escapeIdent($value);
					break;
				case 'i':
					$part = $this->escapeInt($value);
					break;
				case 'a':
					$part = $this->createIN($value);
					break;
				case 'u':
					$part = $this->createSET($value);
					break;
				case 'p':
					$part = $value;
					break;
			}
			$out .= $part;
		}
		return $out;
	}

	private function escapeInt($value)
	{
		if ($value === NULL)
		{
			return 'NULL';
		}
		if(!is_numeric($value))
		{
			$this->error("Integer (?i) placeholder expects numeric value, ".gettype($value)." given");
			return FALSE;
		}
		if (is_float($value))
		{
			$value = number_format($value, 0, '.', ''); // may lose precision on big numbers
		} 
		return $value;
	}

	private function escapeString($value)
	{
		if ($value === NULL)
		{
			return 'NULL';
		}
		return	"'".mysqli_real_escape_string($this->conn,$value)."'";
	}

	private function escapeIdent($value)
	{
		if ($value)
		{
			return "`".str_replace("`","``",$value)."`";
		} else {
			$this->error("Empty value for identifier (?n) placeholder");
		}
	}

	private function createIN($data)
	{
		if (!is_array($data))
		{
			$this->error("Value for IN (?a) placeholder should be array");
			return;
		}
		if (!$data)
		{
			return 'NULL';
		}
		$query = $comma = '';
		foreach ($data as $value)
		{
			$query .= $comma.$this->escapeString($value);
			$comma  = ",";
		}
		return $query;
	}

	private function createSET($data)
	{
		if (!is_array($data))
		{
			$this->error("SET (?u) placeholder expects array, ".gettype($data)." given");
			return;
		}
		if (!$data)
		{
			$this->error("Empty array for SET (?u) placeholder");
			return;
		}
		$query = $comma = '';
		foreach ($data as $key => $value)
		{
			$query .= $comma.$this->escapeIdent($key).'='.$this->escapeString($value);
			$comma  = ",";
		}
		return $query;
	}

	private function error($err)
	{
		throw new $this->exname($err);
	}


}
