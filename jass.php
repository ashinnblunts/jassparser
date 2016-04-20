<?php

// by TriggerHappy

class JassCode
{
    public $code, $language;
    
    function __construct($code, $lang='vjass')
    {
        $this->code     = $code;
        $this->language = $lang;
    }
    
    function parse()
    {
        $root = dirname(__FILE__);
        
        // get the language data
        $dir   = $root . '/include/languages/';
        $fname = $dir  . $this->language . ".php";
        
        if (!file_exists($fname))
            return $this->code;

        require($fname);
        
        if (!isset($language_data['KEYWORDS']))
            return $this->code;
    
        $keyword_group = $language_data['KEYWORDS'];
        $keyword_group_size = count($keyword_group);
    
        // split keywords into array with the string as the key
        for ($i = 0; $i < $keyword_group_size; $i++)
        {
            $size = count($keyword_group[$i]->keywords);
            
            for($j = 0; $j < $size; $j++)
                $keyword_style[$keyword_group[$i]->keywords[$j]] = $keyword_group[$i]->style;
        }
        
        // prep variables
        $contents       = html_entity_decode($this->code);
        $contents       = str_replace("?>", htmlentities("?>"), $contents);
        $contents       = str_replace("<?php", htmlentities("<?php"), $contents);
        $output         = '';
        $inError        = false; 
        $inHighlight    = false;
        $inMacroParam   = false; 
        $compileTime    = false;
    
        // remove warnings
        $error_report = error_reporting();
        error_reporting(E_ERROR | E_PARSE);

        $chunks = explode("\n", $contents);
        $chunks = array_chunk($chunks, 100);

        $len = count($chunks);

        for ($chunk=0; $chunk < $len; $chunk++)
        {
            $chunkdata = $chunks[$chunk];
            $contents = '';

            foreach($chunkdata as $data) 
                $contents .= "$data\n";

            // tokenize the code
            $tokens   = token_get_all("<?php\n$contents");
            $arrSize  = count($tokens);

            // loop through each token and process it accordingly
            for ($i = 1; $i < $arrSize; $i++)
            {
                $token    = $tokens[$i]; 
                $text     = $token;
                $old      = $text;
                $init;

                $highlight_list[$i]   = $text;
            
                // assign the next token so we can look ahead
                if (is_array($tokens[$i+1]))
                {
                    $highlight_list[$i+1] = (list($v1, $v2) = $tokens[$i+1]);
                    $highlight_list[$i+1] = $v2;
                }
                else
                {
                    $highlight_list[$i+1] = $tokens[$i+1];
                }

                $continue = false;

                if (is_array($token))
                {
                    // if $token is an array, set the id and text to the respective index
                    list($id, $text)      = $token;
                    $old                  = $text;
                    $highlight_list[$i]   = $text;
                
                    // find a matching token
                    switch ($id)
                    {
                        case T_DOC_COMMENT:
                            $text = "<span class="  . $language_data['STYLE']['COMPILER']   . ">"   . $text . '</span>';
                            break;
                        case T_COMMENT:
                            if ($text[0] == "#")
                            {
                            }
                            elseif($text[2] == "!")
                            {
                               $text = "<span class="  . $language_data['STYLE']['COMPILER'] . ">"  . $text . '</span>';
                            }
                            else
                            {
                                $text = "<span class=" . $language_data['STYLE']['COMMENT']  . ">"  . $text . '</span>';
                            }
                            break;
                        case T_NUM_STRING:
                        case T_STRING_VARNAME:
                        case T_CONSTANT_ENCAPSED_STRING:
                        case T_ENCAPSED_AND_WHITESPACE:
                            if ($text[0] == "'" && (strlen($text) == 3 || strlen($text) == 6)) // raw codes
                            {
                                $text = substr($text, 1, strlen($text)-2);
                                $text = "'<span class=" . $language_data['STYLE']['RAWCODE'] . ">"  . $text . "</span>'";
                            }
                            elseif($text[0] == '"')
                            {
                                $text = "<span class=" . $language_data['STYLE']['STRING']  . ">"   . $text . '</span>';
                            }
                            break;
                        case T_LNUMBER: // integer
                            $text = "<span class="     . $language_data['STYLE']['VALUE']   . ">"   . $text . '</span>';
                            break;
                        case T_DNUMBER: // real
                            $text = "<span class="     . $language_data['STYLE']['VALUE']   . ">"   . $text . '</span>';
                            break;
                        case T_VARIABLE: // textmacro paramaters
                            $text = "<span class="     . $language_data['STYLE']['VALUE']   . ">"   . $text;
                            if ($highlight_list[$i + 1] == '$')
                            {
                                $text .= '$</span>';
                                $i++;
                            }
                            $text .= '</span>';
                            break;
                        case T_WHITESPACE: // ignore multiple whitespaces
                            $output .= $text;
                            $highlight_list[$i] = $highlight_list[$i-1];

                            $continue=true;

                            break;
                        default:
                            break;
                    }
                }
                
                if ($continue) continue;
                if ($text != $old) {  $output .= $text; continue; };

                // check for error highlighting (compileTime for wurst)
                if ($text == $language_data['ERROR-KEY'] || $compileTime == true)
                {
                    if ($inError) // close error highlighting
                    {
                        if ($compileTime)
                        {
                            $compileTime = false;
                            $text        = "$text</span>";
                        }
                        else $text = "</span>";
                        $inError = false;
                    }
                    else
                    {
                        if ($this->language != 'wurst')
                        {
                            $text    = "<span class=" . $language_data['STYLE']['ERROR'] . ">";
                            $inError = true;
                        }
                        else if ($text == $language_data['ERROR-KEY'] && $this->language == 'wurst')
                        {
                            $compileTime = true;
                            $inError     = true;
                            $text        = "<span class=" . $language_data['STYLE']['MEMBER'] . ">" . $language_data['ERROR-KEY'];
                        }
                        
                    }
                }
                elseif ($text == $language_data['HIGHLIGHT-KEY'])
                {
                    if ($inHighlight) // close error highlighting
                        $text = "</span>";
                    else
                        $text    = "<span class=" . $language_data['STYLE']['HIGHLIGHT'] . ">";

                    $inHighlight=!$inHighlight;
                }
                elseif (isset($keyword_style[$text])) // parse keywords
                {
                    $text = '<span class=' . $keyword_style[$text] . '>' . $text . '</span>';
                }
                elseif (isset($language_data['IDENTIFIERS']))
                {
                    if ($highlight_list[$i-1] == $language_data['IDENTIFIERS']['MEMBER']) // highlight struct members
                    {
                        $text = "<span class=" . $language_data['STYLE']['MEMBER'] . ">"  . $text . '</span>';
                    }
                }

                $output .= $text;
            }
        }
        
        // reset error reporting
        error_reporting($error_report);

        // close any un-closed <span>
        if ($inError) $output .= "</span>";
        if ($inHighlight) $output .= "</span>";

        return $output;
    }
}

class KeywordGroup
{
    public $keywords, $style, $link;
    
    function __construct($keywords, $style, $link="")
    {
        if (is_array($keywords))
            $this->keywords = $keywords;
        elseif (is_string($keywords))
            $this->keywords = array($keywords);
        else
            $this->keywords = array('');
        if (isset($link))
            $this->link = $link;
        
        $this->style = $style;
    }
}


?>
