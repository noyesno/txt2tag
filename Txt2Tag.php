<?php

class Txt2Tag {
    function txt_inline_cbk($matches){
      static $n_sup = 0;
      if(!is_array($matches)) return $matches;
      switch($matches[1]){
        case '**':
          return sprintf('<b>%s</b>',$matches[2]);
        case '__':
          return sprintf('<u>%s</u>',$matches[2]);
        case '--':
          return sprintf('<del>%s</del>',$matches[2]);
        case '``':
          return sprintf('<code>%s</code>',$matches[2]);
        case "''":
          return sprintf('%s',$matches[2]);
        case '""':
          return sprintf('<q>%s</q>',$matches[2]);
        case '[[':
          $text = trim($matches[6]);
          if(preg_match('#://#',$matches[2])){
            return sprintf('<a href="%s">%s</a>',$matches[2],htmlspecialchars($text!=''?$text:$matches[2]));
          }else{
            return sprintf('<a href="http://noyesno.net/page/%s">%s</a>',$matches[2],$text!=''?$text:$matches[2]);
          }
        case '((':
          $n_sup++;
          $text = trim($matches[6]);
          return sprintf('<sup>[<a href="%s" title="%s">%s</a>]</sup>',$matches[2],$text, $n_sup);
        case '{{':
          $text = trim($matches[6]);
          return sprintf('<img src="%s" title="%s"/>',$matches[2],$text!=''?$text:$matches[2]);
      }
      return '------';
    }

    function __construct(&$text){
        $this->buf    = &$text;
        $this->p      = 0;
        $this->size   = strlen($text);
        $this->is_protected = false;
        $this->n_blank = 0;
    }

    function html_tag($p){
        $c = $this->buf[$p];
        for($p2= ($c=='/')?($p+1):$p; $p2<$this->size; $p2++){
           $c = $this->buf[$p2];
           if(!($c >= 'a' && $c <='z')){
               break;
           }
        }
        if(!preg_match('/[:\s\/>]/',$c)) return '';
        $tag = substr($this->buf, $p, $p2-$p);
        return $tag;
    }

    function token(){
        $p = $this->p;
        if($p >= $this->size) {
            return null;
        }


        $c  = $this->buf[$p];
        $c0 = ($p   > 0)?$this->buf[$p-1]:"\n";
        $c1 = ($p+1 < $this->size)?$this->buf[$p+1]:'';
        $c2 = ($p+2 < $this->size)?$this->buf[$p+2]:'';

        // $flag_b = $c=="\n";

        if($c == '<' && (($c1 >= 'a' && $c1 <='z') || $c1 == '/')){
            for($p2=$p+2; $this->buf[$p2] != '>' && $this->buf[$p2] != ' ' && $p2<$this->size; $p2++){
            }
            $tag = substr($this->buf,$p+1,$p2-($p+1));

            for($p2; $this->buf[$p2] != '>' && $p2<$this->size; $p2++){
            }
            $text = substr($this->buf,$p,$p2 - $p+1);
            $this->p = $p2 + 1;


            if($this->is_protected && strcmp($tag, '/'.$this->txt_peek_tag(true))!=0){
              $tag = '#text';
            }
        }else{
            $d = 0;
            for($p2=$p; $p2<$this->size; $p2++){
              if($this->buf[$p2] == "\n"){
                $d=1;
                break;
              }else if($this->buf[$p2] == '<'){
                if(!preg_match('#^/?(span|a|em|code|sub|sup|b|i|strong|)$#',$this->html_tag($p2+1))){
                  break;
                }
              }
            }
            
            $text = substr($this->buf,$p,$p2 - $p);
            $this->p = $p2+$d; 
            $tag = '#text';
        }
        return array($tag,$text);
    }

    var $stack = array();
    var $sp    = -1;
    //var $ctag  = array('#root', '#root');

    function parse(){
      while(1){
        list($tag,$text) = $this->token();
        if(!isset($tag)) break;
        if($tag == '#text'){
            print($this->text2html($text));
        }elseif($tag[0] == '/'){ // close tag
            print($this->txt_clean_tag());

            //$tag_name = substr($tag,1);
 
            $c_tag = $this->txt_peek_tag(true);

            if(substr_compare($tag,$c_tag,1) == 0 ){ // <div>...</div>
              print($text);
              $this->txt_pop_tag();

              if('pre' === $c_tag){
                $this->is_protected = false;
              }
            }
        }else{ // start tag
            if($tag == 'pre'){
              $this->is_protected = true;
            }
            $this->txt_push_tag($tag,$text);
            print($text);
        }
      }
      print($this->txt_clean_tag());
    }

    var $txtstack = array(array('#root',null));
    var $txtsp    = 0;
    

    function txt_push_tag($tag,$param=null){
        //$this->ctag = array($tag,$param);
        array_push($this->stack,array($tag,$param)); 
        $this->sp++;
    }

    function txt_pop_tag(){
      if($this->sp<0) return null;

      $this->sp--;
      return array_pop($this->stack);
    }

    function txt_peek_tag($tagonly=0){
      if($this->sp<0) return null;
      return $tagonly?$this->stack[$this->sp][0]:$this->stack[$this->sp];
    }

    function txt_clean_tag($tag=null){
        $html = '';
        $c_tag = $this->txt_peek_tag(true);
        while((isset($tag) && preg_match('/^(.'.$tag.')$/', $c_tag)) || preg_match('/^(table|ul|ol|p|li)$/',$c_tag)){
            $html .= "</$c_tag>\n";
            $this->txt_pop_tag();
            $c_tag = $this->txt_peek_tag(true);
        }
        return $html;
    }


    function txt_inline(&$text){
        return preg_replace_callback(array(
          '#(\[\[)\s*(((http|https|ftp):\/\/)?[^\s\]]+)\s+(\|\s*([\w\.\s/\S]+?))?\s*\]\]#',
          '#(\{\{)\s*(((http|https|ftp):\/\/)?[^\s\]]+)\s*(\|\s*([\w\.\s/\S]+?))?\s*\}\}#',
          '#(\(\()\s*(((http|https|ftp):\/\/)?[^\s\]]+)\s+(\|\s*([\w\.\s/\S]+?))?\s*\)\)#',
          '#(\*\*)([^\*]+)\*\*#',
          '#(\-\-)(.+?)\-\-#',  // <del></del>
          '#(\_\_)(.+?)\_\_#',  // underline <u></u>
          '#("")(.+?)""#',      // code <q></q>
          '#(``)(.+?)``#',      // code <code></code>
          "#('')(.+?)''#"       // protected <span class="tag"></span>
          ),
          array(&$this, 'txt_inline_cbk'),
          $text
        );
    }

    
    function text2html(&$line){
       $html = '';
       $p = max(0,count($this->stack) - 1);
       list($p_tag, $p_text) =  $this->stack[$p];
     
       ob_start();


        if(preg_match('/^```/',$line)){ // code
             $tag = '```'; 
             if($p_tag == $tag){
               $this->txt_pop_tag();
               print('</pre>');
               $this->is_protected = false;
             }else{
               print($this->txt_clean_tag());
               $this->txt_push_tag($tag);
               $language = '';
               if(preg_match('/(\w+)/',$line,$matches)){
                 $language = $matches[1];
               }

               
               printf('<pre class="code %s">',$language);
               $this->is_protected = true;
             }
        }else if(preg_match("/^'''/",$line)){// pre 区块
             $tag = "'''"; 
             if($p_tag == $tag){
               $this->txt_pop_tag();
               print('</pre>');
               $this->is_protected = false;
             }else{
               print($this->txt_clean_tag());
               $this->txt_push_tag($tag);
               print('<pre>');
               $this->is_protected = true;
             }
        }else if(preg_match("/^(<<<|>>>)/",$line,$matches)){// pre 区块
             $tag = $matches[1]; 
             if($p_tag == '<<<'){
               $this->txt_pop_tag();
               print('</div>');
               $this->is_protected = false;
             }else{
               print($this->txt_clean_tag());
               $this->txt_push_tag($tag);
               print('<div class="html">');
               $this->is_protected = true;
             }
        }else if(preg_match('/^"""/',$line)){// blockquote 区块
             print($this->txt_clean_tag());
             $tag = '"""';
             $p = max(0,count($this->stack) - 1);
             list($p_tag, $p_text) =  $this->stack[$p];
             if($p_tag == $tag){
               $this->txt_pop_tag();
               print('</blockquote>');
             }else{
               $this->txt_push_tag($tag);
               $cname = '';
               if(preg_match('/(\w+)/',$line,$matches)){
                 $cname = $matches[1];
               }
              
               printf('<blockquote class="%s">',$cname);
             }
       }else if($this->is_protected){
         if($p_tag == '<<<'){
           echo $line, "\n";
         }else{
           //echo htmlspecialchars($line), "\n";
           echo htmlspecialchars($line);
         }
       }else if(preg_match('/^\-{6,}/',$line)){ // <hr/>
         echo '<hr/>';
       }else if($p_tag != 'p' && preg_match('/^(={1,6})(.*)(={1,6})\s*$/',$line,$matches)){ // ==== Header ====
         $n = strlen($matches[1]);
         $n = 7-$n;
         print($this->txt_clean_tag());
         echo "<h$n>",trim($matches[2],' ='), "</h$n>\n"; 
       }else if(preg_match('/^  (\s*)([\*\-])\s*(.*)/',$line,$matches)){ // list
         $indent = strlen($matches[1]);
         $tag    = $matches[2]=='*'?'ul':'ol';

         if($p_tag != 'li'){
             print($this->txt_clean_tag('p'));
             
             $this->txt_push_tag($tag);
             print("<$tag>");
         }else if($indent >= $p_text+2){
             $this->txt_push_tag($tag, $indent);
             print("\n  <$tag>"); 
         }else if($indent <= $p_text-2){
             $this->txt_pop_tag();
             list($t_tag,$t_param) = $this->txt_pop_tag();
             print("</li></$t_tag>\n</li>\n");
             $this->txt_pop_tag();
         }else{
             $this->txt_pop_tag();
             print('</li>');
         }

         $this->txt_push_tag('li',$indent);
         echo '<li>', trim($this->txt_inline($matches[3]));
       }else if(preg_match('/^\s*[\^\|]/',$line)){ // table
         $text = $line;
         if($p_tag != 'table'){
             print("\n<table class=\"wiki-table\">\n");
             $this->txt_push_tag('table');
             $pos = strrpos($line,'@');
             if($pos > 0){
               $title = trim(substr($text,$pos+1));
               echo '<caption>', htmlspecialchars($title), '</caption>';
               $text = substr($text, 0, $pos);   
             }
         }

         $text = preg_replace(array('/%%\^%%/','/%%\|%%/'), array('&#94;','&#124;'),$text);
         $cols = preg_split('/([\^\|])/',$text,-1,PREG_SPLIT_DELIM_CAPTURE);
         echo '<tr>';
         for($c=1; $c < count($cols)-2; $c +=2 ){
             $t_tag = ($cols[$c] == '^')?'th':'td';
             echo "<$t_tag>", htmlspecialchars($cols[$c+1]), "</$t_tag>";
         }
         echo '</tr>';
       }else if(preg_match('/^\s*$/',$line)){ // blank line: clean 
         print($this->txt_clean_tag());
         $this->n_blank ++;
       }else if(preg_match('/\S+/',$line)){
         if($p_tag != 'p'){
               $this->txt_push_tag('p',0);
               print("\n<p>");
         }
         if($p_text > 0) print("<br/>\n");
         print($this->txt_inline($line));
         $this->stack[$this->sp][1]++;
         $this->n_blank = 0; 
       }else{
           print("$line\n");
       }


    //print($this->txt_clean_tag());
    $html = ob_get_clean();

    return preg_replace('#<br/>\s*</p>#','</p>',$html);
  }

  static function wiki2html($text){
      $lexer = new Txt2Tag($text);
      return $lexer->parse();
  }                                                        
  
} // end Txt2Tag




//Txt2Tag::wiki2html('afasf ``code``sfsf');
