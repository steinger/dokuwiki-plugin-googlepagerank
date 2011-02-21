<?php
/**
 * Plugin: Google Page Rank
 * 
 * based on an alogoritham on PageRank Lookup v1.1 by HM2K (update: 31/01/07) found here: http://www.hm2k.com/projects/pagerank
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Marcel Steinger <sourcecode@steinger.ch>
 */
 
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_googlepagerank extends DokuWiki_Syntax_Plugin {
 
    function getType() { return 'substition'; }
    function getSort() { return 314; }
    /**
          * settings - host and user agent
          */
    public $googlehost = 'toolbarqueries.google.com';
    public $googleua   = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.6) Gecko/20060728 Firefox/1.5';
 
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{pagerank>[^}]*\}\}',$mode,'plugin_googlepagerank');
    }

    /**
          * Handle the match
          */
    function handle($match, $state, $pos, &$handler) {
     
            $match = substr($match,11,-2);
            list($url,$width,$method) = explode(',',$match);
            if ( empty($url)) { $url = "none";}
            if ( empty($width)) { $width = 40;}
            if ( empty($method)) {$method = "style";}
            return array($url,$width,$method);
    }
    /**
          * Create output
          */
    function render($mode, &$renderer, $data) {
        if($mode == 'xhtml'){
            if (!preg_match('/^(http:\/\/)?([^\/]+)/i', $data[0])) { $data[0]='http://'.$data[0]; }
            $pr=$this->getpr($data[0]);
            $pagerank="PageRank: $pr/10";
            //The (old) image method
            if ($data[2] == 'image') {
            $prpos=$data[1]*$pr/10;
            $prneg=$data[1]-$prpos;
            $html='<img src="http://www.google.com/images/pos.gif" width='.$prpos.' height=4 border=0 alt="'.$pagerank.'"><img src="http://www.google.com/images/neg.gif" width='.$prneg.' height=4 border=0 alt="'.$pagerank.'">';
            }
            //The pre-styled method
            if ($data[2] == 'style') {
            $prpercent=100*$pr/10;
            $html='<div style="position: relative; width: '.$data[1].'px; padding: 0; background: #D9D9D9;"><strong style="width: '.$prpercent.'%; display: block; position: relative; background: #5EAA5E; text-align: center; color: #333; height: 4px; line-height: 4px;"><span></span></strong></div>';
            }
            $renderer->doc .= '<a href="'.$data[0].'" title="'.$pagerank.'">'.$html.'</a>';
            return true;   
        }
        return false;
    }
    /**
          * convert a string to a 32-bit integer
          */
    function StrToNum($Str, $Check, $Magic) {
        $Int32Unit = 4294967296;  // 2^32

        $length = strlen($Str);
        for ($i = 0; $i < $length; $i++) {
            $Check *= $Magic; 	
            //If the float is beyond the boundaries of integer (usually +/- 2.15e+9 = 2^31), 
            //  the result of converting to integer is undefined
            //  refer to http://www.php.net/manual/en/language.types.integer.php
            if ($Check >= $Int32Unit) {
                $Check = ($Check - $Int32Unit * (int) ($Check / $Int32Unit));
                //if the check less than -2^31
                $Check = ($Check < -2147483648) ? ($Check + $Int32Unit) : $Check;
            }
            $Check += ord($Str{$i}); 
        }
        return $Check;
    }
    /**
          * genearate a hash for a url
          */
    function HashURL($String) {
        $Check1 = $this->StrToNum($String, 0x1505, 0x21);
        $Check2 = $this->StrToNum($String, 0, 0x1003F);

        $Check1 >>= 2; 	
        $Check1 = (($Check1 >> 4) & 0x3FFFFC0 ) | ($Check1 & 0x3F);
        $Check1 = (($Check1 >> 4) & 0x3FFC00 ) | ($Check1 & 0x3FF);
        $Check1 = (($Check1 >> 4) & 0x3C000 ) | ($Check1 & 0x3FFF);	
        
        $T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) <<2 ) | ($Check2 & 0xF0F );
        $T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000 );
        
        return ($T1 | $T2);
    }
    /**
          *genearate a checksum for the hash string
          */
    function CheckHash($Hashnum) {
        $CheckByte = 0;
        $Flag = 0;

        $HashStr = sprintf('%u', $Hashnum) ;
        $length = strlen($HashStr);
        
        for ($i = $length - 1;  $i >= 0;  $i --) {
            $Re = $HashStr{$i};
            if (1 === ($Flag % 2)) {              
                $Re += $Re;     
                $Re = (int)($Re / 10) + ($Re % 10);
            }
            $CheckByte += $Re;
            $Flag ++;	
        }
        $CheckByte %= 10;
        if (0 !== $CheckByte) {
            $CheckByte = 10 - $CheckByte;
            if (1 === ($Flag % 2) ) {
                if (1 === ($CheckByte % 2)) {
                    $CheckByte += 9;
                }
                $CheckByte >>= 1;
            }
        }

        return '7'.$CheckByte.$HashStr;
    }
    /**
          * return the pagerank checksum hash
          */
    function getch($url) { return $this->CheckHash($this->HashURL($url)); }
    /**
          * return the pagerank figure
          */
    function getpr($url) {
        //global $this->googlehost,$this->googleua;
        $ch = $this->getch($url);
        $fp = fsockopen($this->googlehost, 80, $errno, $errstr, 30);
        if ($fp) {
           $out = "GET /search?client=navclient-auto&ch=$ch&features=Rank&q=info:$url HTTP/1.1\r\n";
           //echo "<pre>$out</pre>\n"; //debug only
           $out .= "User-Agent: $this->googleua\r\n";
           $out .= "Host: $this->googlehost\r\n";
           $out .= "Connection: Close\r\n\r\n";
           fwrite($fp, $out);
           //$pagerank = substr(fgets($fp, 128), 4); //debug only
           //echo $pagerank; //debug only
           while (!feof($fp)) {
                $data = fgets($fp, 128);
                //echo $data;
                $pos = strpos($data, "Rank_");
                if($pos === false){} else{
                    $pr=substr($data, $pos + 9);
                    $pr=trim($pr);
                    $pr=str_replace("\n",'',$pr);
                    return $pr;
                }
           }
           //else { echo "$errstr ($errno)<br />\n"; } //debug only
           fclose($fp);
        }
    }
}
?>