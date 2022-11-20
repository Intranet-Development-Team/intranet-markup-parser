<?php
class IMP
{
    ## Settings ##
    protected $autoURL = true,
        $linkNewTab = true,
        $inlineAllowImg = false,
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

    public function inlineAllowImg(bool $allow): void
    {
        $this->inlineAllowImg = $allow;
    }

    public function setAllowedLinks(string ...$startwith): void
    {
        $this->allowedLinks = $startwith;
    }

    ## Parsers ##

    public function text(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = preg_replace("/((\r(?!\n))|(\r\n))/", "\n", $str); // Unify line breaks indicators
        $str = preg_replace("/\t/", "    ", $str); // Unify spacers

        $lines = explode("\n", "\n" . $str);
        $numberOfLines = count($lines);

        $referenceLinks = []; // [] => [index, reference]
        $referenceImgs = []; // [] => [index, reference]
        $setreferences = []; // [reference => url]

        $openedBlockquotes = [];

        $blockquote = function (int $linesindex = 0) use (&$lines, &$blockquote, $numberOfLines, &$openedBlockquotes, &$referenceLinks, &$referenceImgs, &$setreferences)
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
            $codeblockOpened = false;

            for ($index = $linesindex; $index < $numberOfLines; $index++)
            {
                if (preg_match('/^ *\{(.+?)(?<!\\\\)\}: *((?:' . implode("|", $this->allowedLinks) . ').+) *$/', $lines[$index], $match))
                {
                    $setreferences[$match[1]] = $match[2];
                    $lines[$index] = "";
                    continue;
                }
                preg_match('/^( *)((?:&gt;)*) *(.*)$/', $lines[$index], $match);
                $currentlineblockindent = strlen($match[2]) / 4;

                if ($currentlineblockindent > $originalblockindent)
                {
                    $index = $blockquote($index) - 1;
                    continue;
                }
                else if ($currentlineblockindent < $originalblockindent)
                {
                    if (isset($openedBlockquotes[$currentlineblockindent]) || $currentlineblockindent === 0)
                    {
                        if ($codeblockOpened)
                        {
                            $prepend  .= '</pre></code>';
                            $codeblockOpened = false;
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

                        $lines[$index - 1] .= "</blockquote>";
                        unset($openedBlockquotes[$originalblockindent]);

                        return $index;
                    }
                }
                else
                {
                    $lines[$index] = $match[1] . $match[3];
                }

                if (preg_match('/^( *)((?:\d+\.)|-|\*) +(.+)$/', $lines[$index], $match)) // Ordered and unordered lists (nestable) open
                {
                    if ($codeblockOpened)
                    {
                        $prepend  .= '</pre></code>';
                        $codeblockOpened = false;
                    }
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
                else if (!empty($openedLists) && preg_match('/^ {4,}(.*)$/', $lines[$index], $match))
                {
                    $lines[$index]  = $match[1];
                    if (preg_match('/^ *\`\`\` *$/', $lines[$index]))
                    {
                        $lines[$index]  = "";
                        if ($paragraphOpened)
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }
                        if (!$codeblockOpened)
                        {
                            $prepend .= '<code><pre>';
                            $codeblockOpened = true;
                        }
                        else
                        {
                            $prepend .= '</pre></code>';
                            $codeblockOpened = false;
                        }
                    }
                    if (!$codeblockOpened)
                    {
                        if ($paragraphOpened && preg_match('/^\s*$/', $lines[$index]))
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }


                        if (preg_match('/^ *(#{1,6}(?!#)) *(.+?) *(#{1,6}(?!#))? *$/', $lines[$index], $match)) // Headings 1-6
                        {
                            if ($codeblockOpened)
                            {
                                $prepend .= '</pre></code>';
                                $codeblockOpened = false;
                            }
                            if ($paragraphOpened)
                            {
                                $prepend .= '</p>';
                                $paragraphOpened = false;
                            }
                            $prepend .= '<h' . strlen($match[1]) . '>';
                            $lines[$index]  = $match[2];
                            $append .= '</h' . strlen($match[1]) . '>';
                        }
                        else if (preg_match('/^ *(=|-|#|_|\*)\1{4,} *?$/', $lines[$index])) // Horizontal break
                        {
                            if ($codeblockOpened)
                            {
                                $prepend .= '</pre></code>';
                                $codeblockOpened = false;
                            }
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
                }
                else
                {
                    if (preg_match('/^ *\`\`\` *$/', $lines[$index]))
                    {
                        $lines[$index] = "";
                        if ($paragraphOpened)
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }
                        if (!$codeblockOpened)
                        {
                            $prepend .= '<code><pre>';
                            $codeblockOpened = true;
                        }
                        else
                        {
                            $prepend .= '</pre></code>';
                            $codeblockOpened = false;
                        }
                    }
                    if (!$codeblockOpened)
                    {
                        if (!empty($openedLists))  // Ordered and unordered lists (nestable) close
                        {
                            if ($codeblockOpened)
                            {
                                $prepend .= '</pre></code>';
                                $codeblockOpened = false;
                            }
                            if ($paragraphOpened)
                            {
                                $prepend .= "</p>";
                                $paragraphOpened = false;
                            }
                            $temp = "";
                            foreach ($openedLists as $toclose)
                            {
                                $temp  = "</li></" . $toclose["element"] . ">" . $temp;
                            }
                            $prepend .= $temp;
                            $openedLists = [];
                        }

                        if ($paragraphOpened && preg_match('/^\s*$/', $lines[$index]))
                        {
                            $prepend .= '</p>';
                            $paragraphOpened = false;
                        }

                        if (preg_match('/^ *(#{1,6}(?!#)) *(.+?) *(#{1,6}(?!#))? *$/', $lines[$index], $match)) // Headings 1-6
                        {
                            if ($codeblockOpened)
                            {
                                $prepend .= '</pre></code>';
                                $codeblockOpened = false;
                            }
                            if ($paragraphOpened)
                            {
                                $prepend .= '</p>';
                                $paragraphOpened = false;
                            }
                            $prepend .= '<h' . strlen($match[1]) . '>';
                            $lines[$index]  = $match[2];
                            $append .= '</h' . strlen($match[1]) . '>';
                        }
                        else if (preg_match('/^ *(=|-|#|_|\*)\1{4,} *?$/', $lines[$index])) // Horizontal break
                        {
                            if ($codeblockOpened)
                            {
                                $prepend .= '</pre></code>';
                                $codeblockOpened = false;
                            }
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
                }

                $lines[$index] = preg_replace('/(?<!\\\\)\*\*\*(?=[^*])([^\<\>]+?)(?<!\\\\)\*\*\*/', "<strong><em>$1</em></strong>", $lines[$index]); // Bold and italic
                $lines[$index] = preg_replace('/(?<!\\\\)\*\*(?=[^*])([^\<\>]*?(?:\<(.+?\>)[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\*\*/', "<strong>$1</strong>", $lines[$index]); // Bold
                $lines[$index] = preg_replace('/(?<!\\\\)\*(?=[^*])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\*/', "<em>$1</em>", $lines[$index]); //Italic
                $lines[$index] = preg_replace('/(?<!\\\\)\_\_(?=[^_])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\_\_/', "<u>$1</u>", $lines[$index]); // Underline
                $lines[$index] = preg_replace('/(?<!\\\\)\~\~(?=[^~])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\~\~/', "<s>$1</s>", $lines[$index]); //Strikethrough
                $lines[$index] = preg_replace('/(?<!\\\\)\=\=(?=[^=])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\=\=/', "<mark>$1</mark>", $lines[$index]); // Highlight
                $lines[$index] = preg_replace('/(?<!\\\\)\^\{(?=[^}])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\}(?!\))/', "<sup>$1</sup>", $lines[$index]); // Superscript
                $lines[$index] = preg_replace('/(?<!\\\\)\_\{(?=[^}])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\}(?!\))/', "<sub>$1</sub>", $lines[$index]); // Subscript
                $lines[$index] = preg_replace('/(?<!\\\\)\`(?=[^`])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\`/', "<code>$1</code>", $lines[$index]); // Code
                $lines[$index] = preg_replace('/(?<!\\\\)\$\$(?=[^`])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\$\$/', "<im-tex>$1</im-tex>", $lines[$index]); // Tex expressions (Tex rendering engine must be applied)
                $lines[$index] = preg_replace('/(?<!\\\\)\[(?=[^\]])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\]\(((?:' . implode("|", $this->allowedLinks) . ')[^\)]+?)(?<!\\\\)\)/', "<a href=\"$3\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $lines[$index]); // Link
                preg_match_all('/(?<!\\\\)\[(?=[^\]])[^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\1\>[^\<\>]*?)*?(?<!\\\\)\]\(\{([^\}]+?)\}(?<!\\\\)\)/', $lines[$index], $matches); // Reference Link
                foreach ($matches[2] as $match)
                {
                    $referenceLinks[] = [$index, $match];
                }
                $lines[$index] = preg_replace('/(?<!\\\\)\&lt;(?!(?:&gt;))([^\<\>]+?)(?<!\\\\)\&gt;\(((?:' . implode("|", $this->allowedLinks) . ')[^\)]+?)(?<!\\\\)\)/', "<img src=\"$2\" alt=\"$1\">", $lines[$index]); // Image
                preg_match_all('/(?<!\\\\)\&lt;(?!&gt;)[^\<\>]+?(?<!\\\\)\&gt;\(\{([^\}]+?)(?<!\\\\)\}\)/', $lines[$index], $matches); // Reference Image
                foreach ($matches[1] as $match)
                {
                    $referenceImgs[] = [$index, $match];
                }
                if ($this->autoURL)
                {
                    $lines[$index] = preg_replace('/(?<!(?:<img src=")|(?:<a href="))(?>(?:' . implode("|", $this->allowedLinks) . ')[^\s<>]+)/', "<a href=\"$0\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$0</a>", $lines[$index]); // auto URL
                }
                $lines[$index] = preg_replace('/\\\\(.)/', "$1", $lines[$index]); // Escape characters

                $lines[$index]  = $prepend . $lines[$index]  . $append;
                $prepend = "";
                $append = "";
            }

            if ($codeblockOpened)
            {
                $lines[$index - 1]  .= '</pre></code>';
                $codeblockOpened = false;
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

        foreach ($referenceLinks as $linkref)
        {
            if (isset($setreferences[$linkref[1]]))
            {
                $lines[$linkref[0]] = preg_replace('/(?<!\\\\)\[([^\]]+?)(?<!\\\\)\]\(\{[^\}]+?(?<!\\\\)\}\)/', "<a href=\"" . $setreferences[$linkref[1]] . "\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $lines[$linkref[0]]); // Link
                break;
            }
        }
        foreach ($referenceImgs as $imgref)
        {
            if (isset($setreferences[$imgref[1]]))
            {
                $lines[$imgref[0]] = preg_replace('/(?<!\\\\)\&lt;(?!(?:&gt;))(.+?)(?<!\\\\)\&gt;\(\{[^\}]+?(?<!\\\\)\}\)/', "<img src=\"" . $setreferences[$imgref[1]] . "\" alt=\"$1\">", $lines[$imgref[0]]); // Image
                break;
            }
        }

        return implode("\n", $lines);
    }

    public function line(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);

        $str = preg_replace("/((\r(?!\n))|(\r\n))+/", "", $str); // Remove all line breaks
        $str = preg_replace("/\t/", "    ", $str); // Unify spacers

        $str = preg_replace('/(?<!\\\\)\*\*\*(?=[^*])([^\<\>]+?)(?<!\\\\)\*\*\*/', "<strong><em>$1</em></strong>", $str); // Bold and italic
        $str = preg_replace('/(?<!\\\\)\*\*(?=[^*])([^\<\>]*?(?:\<(.+?\>)[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\*\*/', "<strong>$1</strong>", $str); // Bold
        $str = preg_replace('/(?<!\\\\)\*(?=[^*])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\*/', "<em>$1</em>", $str); //Italic
        $str = preg_replace('/(?<!\\\\)\_\_(?=[^_])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\_\_/', "<u>$1</u>", $str); // Underline
        $str = preg_replace('/(?<!\\\\)\~\~(?=[^~])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\~\~/', "<s>$1</s>", $str); //Strikethrough
        $str = preg_replace('/(?<!\\\\)\=\=(?=[^=])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\=\=/', "<mark>$1</mark>", $str); // Highlight
        $str = preg_replace('/(?<!\\\\)\^\{(?=[^}])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\}/', "<sup>$1</sup>", $str); // Superscript
        $str = preg_replace('/(?<!\\\\)\_\{(?=[^}])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\}/', "<sub>$1</sub>", $str); // Subscript
        $str = preg_replace('/(?<!\\\\)\`(?=[^`])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\`/', "<code>$1</code>", $str); // Code
        $str = preg_replace('/(?<!\\\\)\$\$(?=[^`])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\$\$/', "<im-tex>$1</im-tex>", $str); // Tex expressions (Tex rendering engine must be applied)
        $str = preg_replace('/(?<!\\\\)\[(?=[^\]])([^\<\>]*?(?:\<(.+?)\>[^\<\>]*?\<\/\2\>[^\<\>]*?)*?)(?<!\\\\)\]\(((?:' . implode("|", $this->allowedLinks) . ')[^\)]+?)(?<!\\\\)\)/', "<a href=\"$3\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $str); // Link
        if ($this->inlineAllowImg)
        {
            $lstr = preg_replace('/(?<!\\\\)\&lt;(?!(?:&gt;))([^\<\>]+?)(?<!\\\\)\&gt;\(((?:' . implode("|", $this->allowedLinks) . ')[^\)]+?)(?<!\\\\)\)/', "<img src=\"$2\" alt=\"$1\">", $str); // Image
        }
        if ($this->autoURL)
        {
            $str = preg_replace('/(?<!(?:<img src=")|(?:<a href="))(?>(?:' . implode("|", $this->allowedLinks) . ')[^\s<>]+)(?!\))/', "<a href=\"$0\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$0</a>", $str); // auto URL
        }
        $str = preg_replace('/\\\\(.)/', "$1", $str); // Escape characters
        return $str;
    }
}