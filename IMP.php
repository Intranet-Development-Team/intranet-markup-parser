<?php
class IMP
{
    ## Settings ##
    protected $autoURL = true,
        $linkNewTab = true,
        $allowedLinks = [
            'https?:\/\/',
            'ftps?:\/\/',
            'mailto:',
            'tel:',
            'data:image\/(?:png|gif|jpeg);base64,',
            'irc:',
            'ircs:',
            'git:',
            'ssh:',
            'news:',
            'steam:',
        ];

    public function setAutoUrl(bool $auto): void
    {
        $this->autoURL = $auto;
    }

    public function setLinkNewTab(bool $newtab): void
    {
        $this->linkNewTab = $newtab;
    }

    public function setAllowedLinks(string ...$startwith): void
    {
        $this->allowedLinks = $startwith;
    }

    ## Parsers ##

    private function block(string $str): string
    {
        $lines = explode("\n", "\n" . $str);
        $numberOfLines = count($lines);

        $openedBlockquotes = [];

        $blockquote = function (int $linesindex = 0) use (&$lines, &$blockquote, $numberOfLines, &$openedBlockquotes)
        {
            preg_match('/^ *((?:&gt;)*)(?!(?:&gt;))/', $lines[$linesindex], $match);
            $originalblockindent = strlen($match[1]) / 4;

            $prepend = "";
            $append = "";

            if ($originalblockindent > 0)
            {
                $prepend = "<blockquote>";
                $openedBlockquotes[$originalblockindent] = true;
            }

            $openedLists = []; // array element properties: indent => ["element" => tagname]
            $paragraphOpened = false;

            for ($index = $linesindex; $index < $numberOfLines; $index++)
            {
                preg_match('/^( *)((?:&gt;)*)(?!(?:&gt;))(.*)$/', $lines[$index], $match);
                $currentlineblockindent = strlen($match[2]) / 4;

                if ($currentlineblockindent > $originalblockindent)
                {
                    $index = $blockquote($index) - 1;
                    continue;
                }
                else if ($currentlineblockindent < $originalblockindent)
                {
                    if ($paragraphOpened)
                    {
                        $lines[$index - 1]  .= '</p>';
                        $paragraphOpened = false;
                    }

                    if (!empty($openedLists))
                    {
                        $temp = "";
                        foreach ($openedLists as $toclose)
                        {
                            $temp  = "</li></" . $toclose["element"] . ">" . $temp;
                        }
                        $lines[$index - 1] .= $temp;
                        $openedLists = [];
                    }

                    for ($currentlineblockindent++; $currentlineblockindent <= $originalblockindent; $currentlineblockindent++)
                    {
                        if (isset($openedBlockquotes[$currentlineblockindent]))
                        {
                            $lines[$index - 1] .= "</blockquote>";
                            unset($openedBlockquotes[$currentlineblockindent]);
                        }
                    }

                    return $index;
                }
                else
                {
                    $lines[$index] = $match[1] . $match[3];
                }

                if (preg_match('/^( *)((?:\d+\.)|-|\*) +(.+)$/', $lines[$index], $match)) // Ordered and unordered lists (nestable) open
                {
                    if ($paragraphOpened)
                    {
                        $prepend .= '</p>';
                        $paragraphOpened = false;
                    }

                    $indent = strlen($match[1]);
                    $lastkey = array_key_last($openedLists);
                    $element = ($match[2] === "-" || $match[2] === "*" ? 'ul' : 'ol');

                    if (!empty($openedLists) && !isset($openedLists[$indent]) && $indent < $lastkey)
                    {
                        for ($i = $indent - 1; $i > array_key_first($openedLists); $i--)
                        {
                            if (isset($openedLists[$i]))
                            {
                                break;
                            }
                        }
                        if (($indent - $i) < ($lastkey - $indent))
                        {
                            $indent = $i;
                        }
                        else
                        {
                            $indent = $lastkey;
                        }
                    }

                    if (!empty($openedLists) && $lastkey !== $indent)
                    {
                        if ($indent > $lastkey)
                        {
                            $lines[$index]  = $match[3];
                            $prepend .= '<' . $element . '><li>';
                            $openedLists[$indent] = ["element" => $element];
                        }
                        else
                        {
                            for ($i = $lastkey; $i > $indent; $i--)
                            {
                                if (isset($openedLists[$i]))
                                {
                                    $prepend .= "</li></" . $openedLists[$i]["element"] . ">";
                                    unset($openedLists[$i]);
                                }
                            }
                            $lines[$index]  = $match[3];
                            $prepend .= '<li>';
                        }
                    }
                    else if (!isset($openedLists[$indent]))
                    {
                        $openedLists = [];
                        $lines[$index]  = $match[3];
                        $prepend .= '<' . $element . '><li>';
                        $openedLists[$indent] = ["element" => $element];
                    }
                    else if (isset($openedLists[$indent]))
                    {
                        $prepend .= '</li>';
                        $lines[$index]  = $match[3];
                        $prepend .= '<li>';
                    }
                }
                else if (!empty($openedLists) && preg_match('/^ {4,}(.+)$/', $lines[$index], $match))
                {
                    $lines[$index]  = $match[1];
                    if ($paragraphOpened && preg_match('/^\s*$/', $lines[$index]))
                    {
                        $prepend .= '</p>';
                        $paragraphOpened = false;
                    }

                    if (preg_match('/^ *(#{1,6}(?!#)) *(.+?) *(#{1,6}(?!#))? *$/', $lines[$index], $match)) // Headings 1-6
                    {
                        if ($paragraphOpened)
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }
                        $prepend .= '<h' . strlen($match[1]) . '>';
                        $lines[$index]  = $match[2];
                        $append .= '</h' . strlen($match[1]) . '>';
                    }
                    else if (preg_match('/^ *(=|-|#|_)\1{4,} *?$/', $lines[$index])) // Horizontal break
                    {
                        if ($paragraphOpened)
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }
                        $lines[$index]  = '<hr>';
                    }
                    else if ($paragraphOpened && !preg_match('/^\s*$/', $lines[$index]))
                    {
                        $prepend .= '<br>';
                    }
                    else if (!$paragraphOpened && !preg_match('/^\s*$/', $lines[$index]))
                    {
                        $prepend .= '<p>';
                        $paragraphOpened = true;
                    }
                }
                else
                {
                    if (!empty($openedLists))  // Ordered and unordered lists (nestable) close
                    {
                        foreach ($openedLists as $toclose)
                        {
                            $prepend = "</li></" . $toclose["element"] . ">" . $prepend;
                        }
                        $openedLists = [];
                    }

                    if ($paragraphOpened && preg_match('/^\s*$/', $lines[$index]))
                    {
                        $prepend .= '</p>';
                        $paragraphOpened = false;
                    }

                    if (preg_match('/^ *(#{1,6}(?!#)) *(.+?) *(#{1,6}(?!#))? *$/', $lines[$index], $match)) // Headings 1-6
                    {
                        if ($paragraphOpened)
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }
                        $prepend .= '<h' . strlen($match[1]) . '>';
                        $lines[$index]  = $match[2];
                        $append .= '</h' . strlen($match[1]) . '>';
                    }
                    else if (preg_match('/^ *(=|-|#|_)\1{4,} *?$/', $lines[$index])) // Horizontal break
                    {
                        if ($paragraphOpened)
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }
                        $lines[$index]  = '<hr>';
                    }
                    else if ($paragraphOpened && !preg_match('/^\s*$/', $lines[$index]))
                    {
                        $prepend .= '<br>';
                    }
                    else if (!$paragraphOpened && !preg_match('/^\s*$/', $lines[$index]))
                    {
                        $prepend .= '<p>';
                        $paragraphOpened = true;
                    }
                }
                $lines[$index]  = $prepend . $lines[$index]  . $append;
                $prepend = "";
                $append = "";
            }

            if ($paragraphOpened)
            {
                $lines[$index - 1]  .= '</p>';
                $paragraphOpened = false;
            }

            if (!empty($openedLists))
            {
                $temp = "";
                foreach ($openedLists as $toclose)
                {
                    $temp  = "</li></" . $toclose["element"] . ">" . $temp;
                }
                $lines[$index - 1] .= $temp;
                $openedLists = [];
            }

            foreach ($openedBlockquotes as $k => $value)
            {
                $lines[$index - 1] .= "</blockquote>";
                unset($openedBlockquotes[$k]);
            }

            return $index;
        };
        $blockquote();
        return implode("", $lines);
    }

    private function inline(string $str, bool $imglineheight = false): string
    {
        $str = preg_replace('/\*\*\*(?=[^*])([^\<\>]+?)\*\*\*/', "<strong><em>$1</em></strong>", $str); // Bold and italic
        $str = preg_replace('/(?<!\*)\*\*(?=[^*])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\*\*/', "<strong>$1</strong>", $str); // Bold
        $str = preg_replace('/(?<!\*)\*(?=[^*])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\*/', "<em>$1</em>", $str); //Italic
        $str = preg_replace('/\_\_(?=[^_])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\_\_/', "<u>$1</u>", $str); // Underline
        $str = preg_replace('/\~\~(?=[^~])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\~\~/', "<s>$1</s>", $str); //Strikethrough
        $str = preg_replace('/\=\=(?=[^=])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\=\=/', "<mark>$1</mark>", $str); // Highlight
        $str = preg_replace('/\^\{(?=[^}])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\}/', "<sup>$1</sup>", $str); // Superscript
        $str = preg_replace('/\_\{(?=[^}])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\}/', "<sub>$1</sub>", $str); // Subscript
        $str = preg_replace('/\`\`\`(?=[^`])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\`\`\`/', "<code>$1</code>", $str); // Code
        $str = preg_replace('/\[(?=[^\]])([^\<\>]*?(?:(\<(.+?)\>)[^]*?(\<\/\3\>)[^\<\>]*?)*?)\]\(((?:' . implode("|", $this->allowedLinks) . ')[^\)]+?)\)/', "<a href=\"$5\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $str); // Link
        $str = preg_replace('/\&lt;(?!(?:&gt;))([^\<\>]+?)\&gt;\(((?:' . implode("|", $this->allowedLinks) . ')[^\)]+?)\)/', "<img src=\"$2\" alt=\"$1\" style=\"" . ($imglineheight ? "max-height:1em;width:fit-content;" : "max-width:100%;") . "\">", $str); // Image
        return $str;
    }

    public function text(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = preg_replace("/((\r(?!\n))|(\r\n))/", "\n", $str); // Unify line breaks indicators

        $str = $this->block($str);
        $str = $this->inline($str);
        if ($this->autoURL)
        {
            $str = preg_replace('/(?<!(?:<img src=")|(?:<a href="))(?>(?:' . implode("|", $this->allowedLinks) . ')[^\s<>]+)/', "<a href=\"$0\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$0</a>", $str); // auto URL
        }
        
        return $str;
    }

    public function line(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = preg_replace("/((\r(?!\n))|(\r\n))+/", "", $str); // Remove all line breaks
        $str = $this->inline($str, true);
        if ($this->autoURL)
        {
            $str = preg_replace('/(?<!(?:<img src=")|(?:<a href="))(?>(?:' . implode("|", $this->allowedLinks) . ')[^\s<>]+)(?!\))/', "<a href=\"$0\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$0</a>", $str); // auto URL
        }
        return $str;
    }
}
