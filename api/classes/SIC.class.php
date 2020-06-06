<?php
class SIC {

    private $basepath;
    private $configfile = 'sites-config.php';
    private $historydirectory = 'history';
    private $summaryfile = '_summary-latest.csv';
    private $satelliteData = array();
    private $activeSites = array();
    private $inactiveSites = array();
    private $allSites = array();
    private $satelliteResponse = false;


    function __construct() {
        // set base path (where sites-config.php and index lives)
        $this->basepath = str_replace('api/classes','',dirname(__FILE__));

        if(file_exists($this->basepath.$this->configfile)){
            // load config file
            require_once($this->basepath.$this->configfile);
            
            // sort array
            ksort($sites);

            // get latest data stored in summary CSV
            $latest_data = $this->getSummary(false);

            // add site ID (hash of array key) to array
            foreach($sites as $key => $value){
                $hash = $this->getSiteHash($key);

                // check if we have some latest data in summary
                // -- set defaults
                $ld['sys_ver'] = 'n/a';
                $ld['php_ver'] = 'n/a';
                $ld['sat_ver'] = 'n/a';
                $ld['time'] = "";
                $ld['date'] = "";
                // -- set values from $lastet_data (summary CSV)
                if(array_key_exists($hash,$latest_data)){
                    if(array_key_exists('sys_ver', $latest_data[$hash])){
                        $ld['sys_ver'] = $latest_data[$hash]['sys_ver'];
                    }
                    if(array_key_exists('php_ver', $latest_data[$hash])){
                        $ld['php_ver'] = $latest_data[$hash]['php_ver'];
                    }
                    if(array_key_exists('sat_ver', $latest_data[$hash])){
                        $ld['sat_ver'] = $latest_data[$hash]['sat_ver'];
                    }
                    if(array_key_exists('time', $latest_data[$hash])){
                        $ld['time'] = $latest_data[$hash]['time'];
                    }
                    if(array_key_exists('date', $latest_data[$hash])){
                        $ld['date'] = $latest_data[$hash]['date'];
                    }
                }


                // create new multidimensional array with hash as key
                // but WITHOUT the secret and WITHOUT the url
                $newarray = array(
                    'name' => $key,
                    'sys' => $value['sys'],
                    'sys_ver' => $ld['sys_ver'],
                    'php_ver' => $ld['php_ver'],
                    'sat_ver' => $ld['sat_ver'],
                    'time' => $ld['time'],
                    'date' => $ld['date'],
                    'hash' => $hash,
                );

                // assigning to inactiveSites OR activesites
                if($value['inact']==true){
                    $this->inactiveSites[$hash] = $newarray;
                } else {
                    $this->activeSites[$hash] = $newarray;
                }

                // assigning to allSites 
                $this->allSites[$hash] = $newarray;

                // store data for accessing satellites
                $this->satelliteData[$hash] = array(
                    'sys' => $value['sys'],
                    'secret' => $value['secret'],
                    'url' => $value['url'],
                );
            }


            // add urls to the history (CSV) files
            $history_urls = $this->getAllHistoryUrls(false);
            $this->allSites = $this->combineArrays($history_urls, $this->allSites);
            $this->activeSites = $this->combineArrays($history_urls, $this->activeSites);
            $this->inactiveSites = $this->combineArrays($history_urls, $this->inactiveSites);

            

        } else {
            throw new Exception('SIC: No sites-config.php found!');
        }
    }

        
    /**
     * getAllSitesJSON
     *
     * @param  bool $json JSON (true) output or array (false)
     * @return string|array JSON or array
     */
    public function getAllSites(bool $json=true){
        if($json){
            return json_encode($this->allSites, JSON_PRETTY_PRINT);
        } else {
            return $this->allSites;
        }
        
    }

    /**
     * getActiveSitesJSON
     *
     * @param  bool $json JSON (true) output or array (false)
     * @return string|array JSON or array
     */
    public function getActiveSites(bool $json=true){
        if($json){
            return json_encode($this->activeSites, JSON_PRETTY_PRINT);
        } else {
            return $this->activeSites;
        }
        
    }

    /**
     * getInactiveSitesJSON
     *
     * @param  bool $json JSON (true) output or array (false)
     * @return string|array JSON or array
     */
    public function getInactiveSites(bool $json=true){
        if($json){
            return json_encode($this->inactiveSites, JSON_PRETTY_PRINT);
        } else {
            return $this->inactiveSites;
        }
        
    }
    
    /**
     * getSatelliteSecret
     *
     * @param  string $hash
     * @return string|false satellite secret or false
     */
    public function getSatelliteSecret(string $hash){
        if(array_key_exists($hash,$this->satelliteData)){
            return $this->satelliteData[$hash]['secret'];
        } else {
            return false;
        }
    }

    /**
     * getSatelliteUrl
     *
     * @param  string $hash
     * @return string|false satellite url or false
     */
    public function getSatelliteUrl(string $hash){
        if(array_key_exists($hash,$this->satelliteData)){
            return $this->satelliteData[$hash]['url'];
        } else {
            return false;
        }
    }

    /**
     * getSiteSystem
     *
     * @param  string $hash
     * @return string|false satellite url or false
     */
    public function getSiteSystem(string $hash){
        if(array_key_exists($hash,$this->satelliteData)){
            return $this->satelliteData[$hash]['sys'];
        } else {
            return false;
        }
    }

    /**
     * getSiteName
     *
     * @param  string $hash
     * @return string|false site name or false
     */
    public function getSiteName(string $hash){
        if(array_key_exists($hash,$this->allSites)){
            return $this->allSites[$hash]['name'];
        } else {
            return false;
        }
    }
    
    /**
     * getSatelliteResponse
     *
     * @param  string $hash
     * @param  bool $json JSON (true) output or array (false)
     * @return string|array|false JSON response or array, false if failed
     */
    public function getSatelliteResponse(string $hash, bool $json=true){
        if(array_key_exists($hash,$this->satelliteData)){
            // set payload and url for request
            $payload = array(
                'sys' => $this->getSiteSystem($hash),
                'secret' => $this->getSatelliteSecret($hash)
            );
            $url = $this->getSatelliteUrl($hash);
            
            $response = $this->sendPostRequest($url,$payload);
            if($response AND array_key_exists('statuscode', $response)){

                // HTTP statuscode 200 (ok)
                if($response['statuscode']==200){
                    $response['message'] = "OK";
                    $response['time'] = date('H:i:s');
                    $response['date'] = date('d.m.Y');
                    $response['hash'] = $hash;
                    $response['name'] = $this->getSiteName($hash);

                    // store response for later use
                    $this->satelliteResponse = array(
                        'hash' => $hash,
                        'response' => $response['response'],
                        'date' => $response['date'],
                        'time' => $response['time']
                    );
                // HTTP statuscode 403 (forbidden)
                } elseif($response['statuscode']==403){
                    $response['message'] = "Authorisatzion failed";
                    $response['hash'] = $hash;
                    $response['name'] = $this->getSiteName($hash);
                } 
                // HTTP statuscode 404 (not found)
                elseif($response['statuscode']==404){
                    $response['message'] = "Satellite not found";
                    $response['hash'] = $hash;
                    $response['name'] = $this->getSiteName($hash);
                } else{
                    return false;
                }

                // json or array output?
                if($json){
                    return json_encode($response, JSON_PRETTY_PRINT);
                } else {
                    return $response;
                } 
                
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    
    /**
     * getCSVFileName
     *
     * @source https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
     * @param  string $filename
     * @return string sanitized filename
     */
    private function getCsvFileName(string $filename){
        return mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $filename).".csv";
    }

    
    /**
     * getCsvSavePath
     *
     * @param  string $hash
     * @return string|void server save path of CSV file
     */
    public function getCsvSavePath(string $hash){
        $sitename = $this->getSiteName($hash);
        if($sitename){
            $filename = $this->getCsvFileName($sitename);
            return $this->basepath.$this->historydirectory."/".$filename;
        }
        
    }


    /**
     * getCsvSaveUrl
     *
     * @param  string $hash
     * @return string|void relative URL of CSV file (relative to main frontend)
     */
    public function getCsvSaveUrl(string $hash){
        $sitename = $this->getSiteName($hash);
        if($sitename){
            $filename = $this->getCsvFileName($sitename);
            return "api/tools/historyviewer.php?id=".$hash;
        }
    }

    /**
     * saveToCSV
     *
     * @return boolean
     */
    public function saveToCSV(){
        if($this->satelliteResponse AND is_array($this->satelliteResponse)){
            
            // set target file
            $targetFile = $this->getCsvSavePath($this->satelliteResponse['hash']);
            
            // get latest satellite response stored in object
            $satdata = json_decode($this->satelliteResponse['response'], true);

            // open / create & open file
            if (file_exists($targetFile)) {
                // open file if already exisits
                $fh = fopen($targetFile, 'a');
            } else {
                // create file and open if not already in place
                $fh = fopen($targetFile, 'w');
                // create table header in CSV
                fputcsv($fh,array('System','Sys Ver','PHP Ver','Sat Ver', 'Date', 'Time'));
            }


            // prepare data for csv
            // - fallbacks
            $sys_ver = "n/a";
            $php_ver = "n/a";
            $sat_ver = "n/a";
            $sys = "n/a";


            // - overwrite fallbacks if there is data
            if(isset($satdata['sys_ver']) AND $satdata['sys_ver']!=''){
                $sys_ver = $satdata['sys_ver'];
            };
            if(isset($satdata['php_ver']) AND $satdata['php_ver']!=''){
                $php_ver = $satdata['php_ver'];
            };
            if(isset($satdata['sat_ver']) AND $satdata['sat_ver']!=''){
                $sat_ver = $satdata['sat_ver'];
            };
            $system = $this->getSiteSystem($this->satelliteResponse['hash']);
            if($system AND $system!=''){
                $sys = $system;
            };

            // write data to CSV;
            fputcsv($fh,array($sys ,$sys_ver, $php_ver, $sat_ver, date('d.m.Y'), date('H:i:s')));

            // close file handle
            fclose($fh);

            return true;
        }
        return false;
    }

    
    /**
     * getAllHistoryUrls
     *
     * @param  bool $json JSON (true) output or array (false)
     * @return string|array JSON or array
     */
    public function getAllHistoryUrls(bool $json=true){
        $sites = $this->getAllSites(false);
        $output = array();
        foreach($sites as $hash=>$value){
            $path = $this->getCsvSavePath($hash);
            if(file_exists($path)){
                $output[$hash] = array(
                    'history' => $this->getCsvSaveUrl($hash)
                );
            }
        }
        if($json){
            return json_encode($output, JSON_PRETTY_PRINT);
        } else {
            return $output;
        }
        
    }

    /**
     * getSummary
     *
     * Creates an array from the summary CSV
     * with the site name as array index
     *
    * @param  bool $json JSON (true) output or array (false)
     * @return string|array JSON or array of summary data
     */
    public function getSummary($json=true){
        $targetFile = $this->basepath.$this->historydirectory."/".$this->summaryfile;
        if(file_exists($targetFile)){
            $f = fopen($targetFile, "r");
            $line_number = 1;
            while (($line = fgetcsv($f)) !== false) {
                // ignore first line, because it contains just the table header
                if($line_number!=1){
                    
                    // skip empty lines
                    if($line[0] == null){continue;};

                    // check if expected columns exists (skip if not)
                    if(!array_key_exists(2,$line) OR
                        !array_key_exists(3,$line) OR
                        !array_key_exists(4,$line) OR
                        !array_key_exists(5,$line) OR
                        !array_key_exists(6,$line)
                        ){
                            continue;
                        }

                    // create array
                    $array[$this->getSiteHash($line[0])]=array(
                        'sys_ver' => $line[2],
                        'php_ver' => $line[3],
                        'sat_ver' => $line[4],
                        'date' => $line[5],
                        'time' => $line[6],
                    );
                }
                $line_number++;
                
            }
            fclose($f);
            if($json){
                return json_encode($array, JSON_PRETTY_PRINT);
            } else {
                return $array;
            }
            
        }
        return array();
        
    }


    /**
     * sendPostRequest
     *
     * Sends a POST request to a given URI
     *
     * @param string $url The destination URI
     * @param array $data Array of POST data (key => value)
     *
     * @return array|false Response from given URL and statuscode or false
     */
    private function sendPostRequest($url,$data){
        if(function_exists(('curl_version'))){

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //causes no output without echo
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // disable verfication of authenticity of the peer's certificate
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            $response['response'] = curl_exec($curl);
            $response['statuscode'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            return $response;

        } else {
            return false;
        }
    }
    
    /**
     * getSiteHash
     *
     * @param  string $string
     * @return string hashed string
     */
    private function getSiteHash(string $string){
        return md5($string);
    }

    /**
     * getActiveSitesSystems
     * 
     * Returns systems and their count
     *
     * @param  bool $json JSON (true) output or array (false)
     * @return string|array JSON or array
     */
    public function getActiveSitesSystems(bool $json=true){
        $systems = array();
        foreach($this->activeSites as $site){
            $sys = $site['sys'];
            if(!array_key_exists($sys,$systems)){
                $systems[$sys] = 1;
            } else {
                $systems[$sys]++;
            }
        }
        ksort($systems);
        if($json){
            return json_encode($systems, JSON_PRETTY_PRINT);
        } else {
            return $systems;
        }
    }

    
    /**
     * combineArrays
     * 
     * Combines a $source array with a $target array making sure
     * that no 1st-level-entry is added to $taget and also that
     * no 2nd-level is overwritten. It only adds 2nd-level-entries
     * from $source if they are not already present in $target.
     *
     * @param  array $source
     * @param  array $target
     * @return array
     */
    public function combineArrays(array $source, array $target){
        $output = array();
        foreach($target as $t_key=>$t_value){
            // first take the orinal data
            $output[$t_key] = $t_value;
            
            // check if the current key is also availabe in $source
            if(array_key_exists($t_key,$source)){
                
                // iterate $source second level array
                foreach($source[$t_key] as $s_key=>$s_value){

                    // only add new entry if not already present
                    if(!array_key_exists($s_key,$output[$t_key])){
                        $output[$t_key][$s_key] = $s_value;
                    }
                }
            }
        }
        return $output;
    }

    
    /**
     * writeSummaryAndGetUrl
     *
     * @param  bool $json JSON (true) output or plain text (false)
     * @return mixed JSON or plain text or bool (false)
     */
    public function writeSummaryAndGetUrl(bool $json=true){
        $sites = $this->getActiveSites(false);
        $targetFile = $this->basepath.$this->historydirectory."/".$this->summaryfile;
        $paths = array();

        // get array of all existing history files for active sites
        foreach($sites as $hash=>$value){
            $path = $this->getCsvSavePath($hash);
            if(file_exists($path)){
                $paths[$hash] = $path;
            }
        }

        // read last lines from each history file
        $lines = "";
        $index = 1;
        foreach($paths as $hash=>$file){
            $name = $this->getSiteName($hash);
            // replace csv critical chars from $name
            // (we don't need to care about $data, because this data was already from CSV)
            $name = str_replace('"','',$name);
            $name = str_replace(',',' ',$name);
            $name = '"'.$name.'"';

            // get last line from site history file
            $data = $this->tailCustom($file);

            // store line in $lines, add line break (PHP_EOL) before each line
            // but not before the first line
            if($index!=1){
                $prefix = PHP_EOL;
            } else {
                $prefix = "";
            }
            $lines.= $prefix.$name.','.$data;

            $index++;
        };

        // create summary file
        if($lines!=""){

            // delete old summary file
            if (file_exists($targetFile)) { 
                unlink($targetFile);
            }

            // open summary file
            $fp = fopen($targetFile, 'w');
            
            // create table header in CSV
            fputcsv($fp,array('Site','System','Sys Ver','PHP Ver','Sat Ver', 'Date', 'Time'));

            // write lines
            fwrite($fp, $lines);
            fclose($fp);
        }

        // check if summary file exists and get url
        return $this->checkSummaryFileAndGetUrl($json);

    }

    
    /**
     * checkSummaryFileAndGetUrl
     *
     * @param  bool $json JSON (true) output or plain text (false)
     * @return mixed JSON or plain text or bool (false)
     */
    public function checkSummaryFileAndGetUrl(bool $json=true){
        $targetFile = $this->basepath.$this->historydirectory."/".$this->summaryfile;
        $fileurl = $this->historydirectory."/".$this->summaryfile;
        
        // check if summary file exists
        if(file_exists($targetFile)){
            $returnvalue = $fileurl;
        } else {
            $returnvalue = false;
        }


        if($json){
            return json_encode($returnvalue, JSON_PRETTY_PRINT);
        } else {
            return $returnvalue;
        }
    }


    /**
     * tailCustom
     * 
     * Gets the last line of a given file
     * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
	 * @author Torleif Berger, Lorenzo Stanco
	 * @link http://stackoverflow.com/a/15025877/995958
	 * @license http://creativecommons.org/licenses/by/3.0/
     * @source https://gist.github.com/lorenzos/1711e81a9162320fde20
     * 
     * @param string $filepath
     * @param int $lines Number of lines to get (default = 1)
     * @param bool $adaptive Set memory adaptive mode (default = true)
	 */
    private function tailCustom(string $filepath, int $lines = 1, bool $adaptive = true) {

       // Open file
       $f = @fopen($filepath, "rb");
       if ($f === false) return false;

       // Sets buffer size, according to the number of lines to retrieve.
       // This gives a performance boost when reading a few lines from the file.
       if (!$adaptive) $buffer = 4096;
       else $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));

       // Jump to last character
       fseek($f, -1, SEEK_END);

       // Read it and adjust line number if necessary
       // (Otherwise the result would be wrong if file doesn't end with a blank line)
       if (fread($f, 1) != "\n") $lines -= 1;
       
       // Start reading
       $output = '';
       $chunk = '';

       // While we would like more
       while (ftell($f) > 0 && $lines >= 0) {

           // Figure out how far back we should jump
           $seek = min(ftell($f), $buffer);

           // Do the jump (backwards, relative to where we are)
           fseek($f, -$seek, SEEK_CUR);

           // Read a chunk and prepend it to our output
           $output = ($chunk = fread($f, $seek)) . $output;

           // Jump back to where we started reading
           fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

           // Decrease our line counter
           $lines -= substr_count($chunk, "\n");

       }

       // While we have too many lines
       // (Because of buffer size we might have read too many)
       while ($lines++ < 0) {

           // Find first newline and remove all text before that
           $output = substr($output, strpos($output, "\n") + 1);

       }

       // Close file and return
       fclose($f);
       return trim($output);

   }

} 