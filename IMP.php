<?php
class IMP
{
    ## Settings ##
    protected $autoURL = true,
        $allowedLinks = [
            'https?:\/\/',
            'ftps?:\/\/',
            'mailto:',
            'tel:',
            'data:image\/png;base64,',
            'data:image\/gif;base64,',
            'data:image\/jpeg;base64,',
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

    public function setAllowedLinks(string ...$startwith): void
    {
        $this->allowedLinks = $startwith;
    }

    ## Parsers ##

    private function block(string $str): string
    {
        $lines = explode("\n", $str);
        $openedLists = []; // array element properties: indent => ["element" => tagname]

        foreach ($lines as &$line)
        {
            if (preg_match('/^( *)((?:\d+\.)|-) +.+$/', $line, $match)) // Ordered and unordered lists (nestable)
            {
                $indent = strlen($match[1]);
                $lastkey = array_key_last($openedLists);
                $element = ($match[2] === "-" ? 'ul' : 'ol');

                if (isset($openedLists[$indent]))
                {
                    $line = '</li>' . $line;
                }

                if (!empty($openedLists) && $lastkey !== $indent)
                {
                    if ($indent > $lastkey)
                    {
                        $line = preg_replace('/ *((?:\d+\.)|-) +/', '<' . $element . '><li>', $line, 1);
                        $openedLists[$indent] = ["element" => $element];
                    }
                    else
                    {
                        for (; $lastkey > $indent; $lastkey--)
                        {
                            if (isset($openedLists[$lastkey]))
                            {
                                $line = "</li></" . $openedLists[$lastkey]["element"] . ">" . $line;
                                unset($openedLists[$lastkey]);
                            }
                        }
                        $line = preg_replace('/ *((?:\d+\.)|-) +/', '<li>', $line, 1);
                    }
                }
                else if (!isset($openedLists[$indent]))
                {
                    $openedLists = [];
                    $openedLists[$indent] = ["element" => $element];
                    $line = preg_replace('/ *((?:\d+\.)|-) +/', '<' . $element . '><li>', $line, 1);
                }

                if (isset($openedLists[$indent]))
                {
                    $line = preg_replace('/ *((?:\d+\.)|-) +/', '<li>', $line, 1);
                }
            }
            else
            {
                if (!empty($openedLists))
                {
                    foreach ($openedLists as $toclose)
                    {
                        $line = "</li></" . $toclose["element"] . ">\n" . $line;
                    }
                    $openedLists = [];
                }
                if (preg_match('/^ *(#{1,6}(?!#)) *(.+?)$/', $line, $match)) // Headings 1-6
                {
                    $line = '<h' . strlen($match[1]) . '>' . $match[2] . '</h' . strlen($match[1]) . '>';
                }
                else if (preg_match('/^ *&gt; *(.+?)?$/', $line, $match)) // Blockquote
                {
                    $line = '<blockquote>' . $match[1] . '</blockquote>';
                }
                else if (preg_match('/^ *(=|-|#)\1{4,} *?$/', $line, $match)) // Horizontal break
                {
                    $line = '<hr>';
                }
                $line = preg_replace('/(?:^|\n)([^<>]+?)(?:$|\n)/', '<p>${1}</p>', $line); // Paragraph
            }
        }
        return implode("\n", $lines);
    }

    private function inline(string $str): string
    {
        $str = preg_replace('/\*\*\*(?=[^*]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\*\*\*/', "<strong><em>$1</em></strong>", $str); // Bold and italic
        $str = preg_replace('/\*\*(?=[^*]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\*\*/', "<strong>$1</strong>", $str); // Bold
        $str = preg_replace('/\*(?=[^*]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\*/', "<em>$1</em>", $str); //Italic
        $str = preg_replace('/\_\_(?=[^_]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\_\_/', "<u>$1</u>", $str); // Underline
        $str = preg_replace('/\~\~(?=[^~]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\~\~/', "<s>$1</s>", $str); //Strikethrough
        $str = preg_replace('/\=\=(?=[^=]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\=\=/', "<mark>$1</mark>", $str); // Highlight
        $str = preg_replace('/\^\{(?=[^}]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\}/', "<sup>$1</sup>", $str); // Superscript
        $str = preg_replace('/\_\{(?=[^}]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\}/', "<sub>$1</sub>", $str); // Subscript
        $str = preg_replace('/\`\`\`(?=[^`]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\`\`\`/', "<code>$1</code>", $str); // Code
        $str = preg_replace('/(?<!\!)\[(?!\[)(?=[^\]]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)(?<!\])\]\(((?:'.implode("|",$this->allowedLinks).')[^\)]+?)\)/', "<a target=\"_blank\" href=\"$5\">$1</a>", $str); // Link
        $str = preg_replace('/\!\[\[(?=[^\]]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\]\]\(((?:'.implode("|",$this->allowedLinks).')[^\)]+?)\)/', "<img src=\"$5\" alt=\"$1\">", $str); // Image
        return $str;
    }

    public function text(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = preg_replace("/((\r(?!\n))|(\r\n))+/", "\n", $str); // Unify line breaks indicators and remove excess breaks
        if ($this->autoURL)
        {
            $str = preg_replace('/(?<!\]\()(?>(?:'.implode("|",$this->allowedLinks).')[^\s]+)(?!\]\()/', "<a target=\"_blank\" href=\"$0\">$0</a>", $str); // auto URL
        }
        $str = $this->block($str);
        $str = $this->inline($str);
        $str = preg_replace("/\n/", "", $str);
        return $str;
    }

    public function line(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = preg_replace("/((\r(?!\n))|(\r\n))+/", "", $str); // Remove all line breaks
        if ($this->autoURL)
        {
            $str = preg_replace('/(?<!\]\()(?>(?:'.implode("|",$this->allowedLinks).')[^\s]+)(?!\]\()/', "<a target=\"_blank\" href=\"$0\">$0</a>", $str); // auto URL
        }
        $str = $this->inline($str);
        return $str;
    }
}
?>