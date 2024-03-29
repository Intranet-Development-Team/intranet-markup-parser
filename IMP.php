<?php
class IMP
{
    ## Settings ##

    private $autoURL = true,
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

    ## Multiline Parser ##

    private $lines = [], $prepends = [], $appends = [], $index = 0, $total = 0;
    private $linkreference = []; // [reference name => url]

    public function text(string $str): string
    {
        $str = preg_replace('/\r(?!\n)|\r\n/', "\n", $str); // Unify line breaks indicators
        $str = preg_replace('/\t/', "    ", $str); // Unify spacers
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, null, false); // Escape HTML

        $this->lines = explode("\n", $str);
        $this->total = count($this->lines) - 1;
        $this->prepends = array_fill(0, count($this->lines), "");
        $this->appends = array_fill(0, count($this->lines), "");

        // Translator
        for ($this->index = 0; $this->index <= $this->total; $this->index++)
        {
            $this->processor();
        }

        // Join all the things together
        $returnstr = "";
        foreach ($this->lines as $key => $line)
        {
            $line = preg_replace('/(?<!\\\\)\*\*\*(?!\*)(.+?)(?<!\\\\)\*\*\*/', "<strong><em>$1</em></strong>", $line); // Bold and italic
            $line = preg_replace('/(?<!\\\\)\*\*(?!\*)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\*\*/', "<strong>$1</strong>", $line); // Bold
            $line = preg_replace('/(?<!\\\\)\*(?!\*)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\*/', "<em>$1</em>", $line); //Italic
            $line = preg_replace('/(?<!\\\\)__(?!_)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)__/', "<u>$1</u>", $line); // Underline
            $line = preg_replace('/(?<!\\\\)~~(?!~)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)~~/', "<s>$1</s>", $line); //Strikethrough
            $line = preg_replace('/(?<!\\\\)==(?!=)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)==/', "<mark>$1</mark>", $line); // Highlight
            $line = preg_replace('/(?<!\\\\)\^\{(?!\})([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\}/', "<sup>$1</sup>", $line); // Superscript
            $line = preg_replace('/(?<!\\\\)_\{(?!\})([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\}/', "<sub>$1</sub>", $line); // Subscript
            $line = preg_replace('/(?<!\\\\)`(?!`)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)`/', "<code>$1</code>", $line); // Code
            $line = preg_replace_callback('/(?<!\\\\)!\[([^<>]*?)(?<!\\\\)\]\(((?:' . implode("|", $this->allowedLinks) . ')[^<>]+?)(?<!\\\\)\)(?:&lt;([0-9]+)(?<!\\\\)&gt;)?/', function ($match)
            {
                return '<figure class="figure"><img src="' . $match[2] . '" alt="' . $match[1] . '" style="max-width: 100%;width: ' . (empty($match[3]) ? '100%' : $match[3] . 'em') . ';" class="rounded"><figcaption class="figure-caption">' . $match[1] . '</figcaption></figure>';
            }, $line); // Image
            $line = preg_replace_callback('/(?<!\\\\)!\[([^<>]*?)(?<!\\\\)\]\(([^<>]+?)(?<!\\\\)\)(?:&lt;([0-9]+)(?<!\\\\)&gt;)?/', function ($match)
            {
                if (isset($this->linkreference[$match[2]]))
                {
                    return '<figure class="figure"><img src="' . $this->linkreference[$match[2]] . '" alt="' . $match[1] . '" style="max-width: 100%;width: ' . (empty($match[3]) ? '100%' : $match[3] . 'em') . ';" class="rounded"><figcaption class="figure-caption">' . $match[1] . '</figcaption></figure>';
                }
                else
                {
                    return $match[0];
                }
            }, $line); // Image with reference
            $line = preg_replace('/(?<!\\\\)\[(?!\])([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\]\(((?:' . implode("|", $this->allowedLinks) . ')[^<>]+?)(?<!\\\\)\)/', "<a href=\"$3\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $line); // Link with text
            $line = preg_replace('/(?<!\\\\)&lt;((?:' . implode("|", $this->allowedLinks) . ')[^<>]+?)(?<!\\\\)&gt;/', "<a href=\"$1\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $line); // Link with indication
            $line = preg_replace_callback('/(?<!\\\\)\[(?!\])([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\]\(([^<>]+?)(?<!\\\\)\)/', function ($match)
            {
                if (isset($this->linkreference[$match[3]]))
                {
                    return "<a href=\"" . $this->linkreference[$match[3]] . "\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">" . $match[1] . "</a>";
                }
                else
                {
                    return $match[0];
                }
            }, $line); // Link with reference
            if ($this->autoURL)
            {
                $line = preg_replace('/(?<!<a href="|<img src=")(?:' . implode("|", $this->allowedLinks) . ')[-a-zA-Z0-9@:%._\\\\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\\\\+.~#?&\/=]*)(?! *<\/a>)/', "<a href=\"$0\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$0</a>", $line); // auto URL
            }

            $line = preg_replace_callback('/\\\\([^&]|&lt;|&gt;)/', function ($match)
            {
                if ($match[1] == '\\' || $match[1] == '`' || $match[1] == '*' || $match[1] == '_' || $match[1] == '{' || $match[1] == '}' || $match[1] == '[' || $match[1] == ']' || $match[1] == '&lt;' || $match[1] == '&gt;' || $match[1] == '(' || $match[1] == ')' || $match[1] == '#' || $match[1] == '+' || $match[1] == '-' || $match[1] == '.' || $match[1] == '!' || $match[1] == '|' || $match[1] == '~' || $match[1] == '^')
                {
                    return $match[1];
                }
                else
                {
                    return '\\' . $match[1];
                }
            }, $line); // Remove backslash in front of escaped characters

            $returnstr .= $this->prepends[$key] . $line . $this->appends[$key];
        }

        return $returnstr;
    }

    private function processor(int|null $until = null): void
    {
        if (!isset($until))
        {
            $until = $this->total;
        }

        if (preg_match('/^ *# *[^ #]/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = preg_replace('/^ *# */', "", $this->lines[$this->index]);
            $this->lines[$this->index] = preg_replace('/ *# *$/', "", $this->lines[$this->index]);
            $this->prepends[$this->index] .= "<h1>" . $this->prepends[$this->index];
            $this->appends[$this->index] .= "</h1>";
        }
        else if (preg_match('/^ *## *[^ #]/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = preg_replace('/^ *## */', "", $this->lines[$this->index]);
            $this->lines[$this->index] = preg_replace('/ *## *$/', "", $this->lines[$this->index]);
            $this->prepends[$this->index] .= "<h2>" . $this->prepends[$this->index];
            $this->appends[$this->index] .= "</h2>";
        }
        else if (preg_match('/^ *### *[^ #]/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = preg_replace('/^ *### */', "", $this->lines[$this->index]);
            $this->lines[$this->index] = preg_replace('/ *### *$/', "", $this->lines[$this->index]);
            $this->prepends[$this->index] .= "<h3>" . $this->prepends[$this->index];
            $this->appends[$this->index] .= "</h3>";
        }
        else if (preg_match('/^ *#### *[^ #]/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = preg_replace('/^ *#### */', "", $this->lines[$this->index]);
            $this->lines[$this->index] = preg_replace('/ *#### *$/', "", $this->lines[$this->index]);
            $this->prepends[$this->index] .= "<h4>" . $this->prepends[$this->index];
            $this->appends[$this->index] .= "</h4>";
        }
        else if (preg_match('/^ *##### *[^ #]/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = preg_replace('/^ *##### */', "", $this->lines[$this->index]);
            $this->lines[$this->index] = preg_replace('/ *##### *$/', "", $this->lines[$this->index]);
            $this->prepends[$this->index] .= "<h5>" . $this->prepends[$this->index];
            $this->appends[$this->index] .= "</h5>";
        }
        else if (preg_match('/^ *###### *[^ #]/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = preg_replace('/^ *###### */', "", $this->lines[$this->index]);
            $this->lines[$this->index] = preg_replace('/ *###### *$/', "", $this->lines[$this->index]);
            $this->prepends[$this->index] .= "<h6>" . $this->prepends[$this->index];
            $this->appends[$this->index] .= "</h6>";
        }
        else if (preg_match('/^ *(\*{3,}|-{3,}|_{3,}|={3,}) *$/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = "";
            $this->prepends[$this->index] .= "<hr>";
        }
        else if (preg_match('/^ *&gt; *[^ ]/', $this->lines[$this->index]))
        {
            $this->blockquote($until);
        }
        else if (preg_match('/^ *```/', $this->lines[$this->index]))
        {
            $this->preformattedBlock_explicit($until);
        }
        else if (preg_match('/^ {4}/', $this->lines[$this->index]))
        {
            $this->preformattedBlock($until);
        }
        else if (preg_match('/^ *[0-9]+\. +[^ ]/', $this->lines[$this->index]))
        {
            $this->orderedList($until);
        }
        else if (preg_match('/^ *(?:-|\+|\*) +[^ ]/', $this->lines[$this->index]))
        {
            $this->unorderedList($until);
        }
        else if (preg_match('/^ *\[.+?(?<!\\\\)\]: *[^ ]/', $this->lines[$this->index]))
        {
            $this->linkreference();
        }
        else if (preg_match('/^ *$/', $this->lines[$this->index])) // Remove empty lines
        {
            $this->lines[$this->index] = "";
        }
        else
        {
            $this->paragraph($until);
        }
    }

    private function blockquote(int $until): void
    {
        // Blockquote opens
        $this->prepends[$this->index] .= '<blockquote class="bg-body-tertiary">';

        for ($i = $this->index; $i <= $until && preg_match('/^ *&gt;/', $this->lines[$i]); $i++)
        {
            $this->lines[$i] = preg_replace('/^ *&gt; */', "", $this->lines[$i], 1);
        }
        $i--;

        // Blockquote closes
        $this->appends[$i] = "</blockquote>" . $this->appends[$i];

        // Translate content
        for (; $this->index <= $i; $this->index++)
        {
            $this->processor($i);
        }
        $this->index--;
    }

    private function preformattedBlock_explicit(int $until): void
    {
        // Preformatted block opens
        $this->prepends[$this->index] .= '<pre class="bg-dark-subtle"><code>';

        $this->lines[$this->index] = ""; // Delete starting identifier

        for (; $this->index < $until - 1 && !preg_match('/^ *```$/', $this->lines[$this->index + 1]);)
        {
            $this->index++;
            $this->appends[$this->index] = "\n";
        }
        $this->index++;

        if (preg_match('/^ *```$/', $this->lines[$this->index]))
        {
            $this->lines[$this->index] = ""; // Delete ending identifier
        }

        // Preformatted block closes
        $this->appends[$this->index] = "</code></pre>" . $this->appends[$this->index];
    }

    private function preformattedBlock(int $until): void
    {
        // Preformatted block opens
        $this->prepends[$this->index] .= '<pre class="bg-dark-subtle"><code>';

        for (; $this->index < $until && preg_match('/^ {4}/', $this->lines[$this->index + 1]); $this->index++)
        {
            $this->lines[$this->index] = preg_replace('/^ {4}/', "", $this->lines[$this->index], 1);
            $this->appends[$this->index] = "\n";
        }
        $this->lines[$this->index] = preg_replace('/^ {4}/', "", $this->lines[$this->index], 1);

        // Preformatted block closes
        $this->appends[$this->index] = "</code></pre>" . $this->appends[$this->index];
    }

    private function orderedList(int $until): void
    {
        $this->prepends[$this->index] .= "<ol>";

        for (; $this->index <= $until && preg_match('/^ *[0-9]+\. /', $this->lines[$this->index]); $this->index++)
        {
            // List item opens
            $this->prepends[$this->index] .= "<li>";
            $this->lines[$this->index] = preg_replace('/^ *[0-9]+\. +/', "", $this->lines[$this->index], 1);

            // Check if list item contains other subelements
            $indent = 0;
            for ($i = $this->index + 1; $i <= $until && preg_match('/^ {' . ($indent ? $indent : 1) . ',}|^$/', $this->lines[$i], $match); $i++)
            {
                if (!$indent)
                {
                    $indent = strlen($match[0]);
                    if ($indent > 4)
                    {
                        $indent = 4;
                    }
                }
                $this->lines[$i] = preg_replace('/^ {' . $indent . '}/', "", $this->lines[$i], 1);
            }
            $i--;

            // List item closes
            if ($i + 1 > $until || !preg_match('/^ *[0-9]+\. /', $this->lines[$i + 1]))
            {
                $this->appends[$i] = "</ol>" . $this->appends[$i]; // List closes
            }
            $this->appends[$i] = "</li>" . $this->appends[$i];

            // Translate content in list item
            for ($this->index++; $this->index <= $i; $this->index++)
            {
                $this->processor($i);
            }
            $this->index--;
        }
        $this->index--;
    }

    private function unorderedList(int $until): void
    {
        $this->prepends[$this->index] .= "<ul>";

        for (; $this->index <= $until && preg_match('/^ *(?:-|\+|\*) /', $this->lines[$this->index]); $this->index++)
        {
            // List item opens
            $this->prepends[$this->index] .= "<li>";
            $this->lines[$this->index] = preg_replace('/^ *(?:-|\+|\*) +/', "", $this->lines[$this->index], 1);

            // Check if list item contains other subelements
            $indent = 0;
            for ($i = $this->index + 1; $i <= $until && preg_match('/^ {' . ($indent ? $indent : 1) . ',}|^$/', $this->lines[$i], $match); $i++)
            {
                if (!$indent)
                {
                    $indent = strlen($match[0]);
                    if ($indent > 4)
                    {
                        $indent = 4;
                    }
                }
                $this->lines[$i] = preg_replace('/^ {' . $indent . '}/', "", $this->lines[$i], 1);
            }
            $i--;

            // List item closes
            if ($i + 1 > $until || !preg_match('/^ *(?:-|\+|\*) /', $this->lines[$i + 1]))
            {
                $this->appends[$i] = "</ul>" . $this->appends[$i]; // List closes
            }
            $this->appends[$i] = "</li>" . $this->appends[$i];

            // Translate content in list item
            for ($this->index++; $this->index <= $i; $this->index++)
            {
                $this->processor($i);
            }
            $this->index--;
        }
        $this->index--;
    }

    private function paragraph(int $until): void
    {
        // Paragraph opens
        $this->prepends[$this->index] .= "<p>";

        for (; $this->index < $until && !preg_match('/(?:^ *(?:- +[^ ]|[0-9]+\. +[^ ]|```|&gt; *[^ ]|(?:\*{3,}|-{3,}|_{3,}|={3,}) *$|#+ *[^ ]|\[.+?\]: *[^ ]|$)|^ {4})/', $this->lines[$this->index + 1]); $this->index++)
        {
            // Translate new lines: line breaks
            $this->appends[$this->index] = "<br>" . $this->appends[$this->index];
        }

        // Paragraph closes
        $this->appends[$this->index] = "</p>" . $this->appends[$this->index];
    }

    private function linkReference(): void
    {
        // Store reference
        preg_match('/^ *\[(.+?)(?<!\\\\)\]: *(.+?) *$/', $this->lines[$this->index], $match);
        if (preg_match('/^(?:' . implode("|", $this->allowedLinks) . ')/', $match[2]))
        {
            $this->linkreference[$match[1]] = $match[2];
            $this->lines[$this->index] = "";
        }
    }

    ## Inline Parser ##

    public function line(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, null, false); // Escape HTML

        $str = preg_replace('/(?<!\\\\)\*\*\*(?!\*)(.+?)(?<!\\\\)\*\*\*/', "<strong><em>$1</em></strong>", $str); // Bold and italic
        $str = preg_replace('/(?<!\\\\)\*\*(?!\*)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\*\*/', "<strong>$1</strong>", $str); // Bold
        $str = preg_replace('/(?<!\\\\)\*(?!\*)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\*/', "<em>$1</em>", $str); //Italic
        $str = preg_replace('/(?<!\\\\)__(?!_)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)__/', "<u>$1</u>", $str); // Understr
        $str = preg_replace('/(?<!\\\\)~~(?!~)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)~~/', "<s>$1</s>", $str); //Strikethrough
        $str = preg_replace('/(?<!\\\\)==(?!=)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)==/', "<mark>$1</mark>", $str); // Highlight
        $str = preg_replace('/(?<!\\\\)\^\{(?!\})([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\}/', "<sup>$1</sup>", $str); // Superscript
        $str = preg_replace('/(?<!\\\\)_\{(?!\})([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\}/', "<sub>$1</sub>", $str); // Subscript
        $str = preg_replace('/(?<!\\\\)`(?!`)([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)`/', "<code>$1</code>", $str); // Code
        $str = preg_replace('/(?<!\\\\)\[(?!\])([^<>]*?(?:<([^<>]+?)>[^<>]*?<\/\2>[^<>]*?)*?)(?<!\\\\)\]\(((?:' . implode("|", $this->allowedLinks) . ')[^<>]+?)(?<!\\\\)\)/', "<a href=\"$3\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $str); // Link with text
        $str = preg_replace('/(?<!\\\\)&lt;((?:' . implode("|", $this->allowedLinks) . ')[^<>]+?)(?<!\\\\)&gt;/', "<a href=\"$1\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$1</a>", $str); // Link with indication
        if ($this->autoURL)
        {
            $str = preg_replace('/(?<!<a href="|<img src=")(?:' . implode("|", $this->allowedLinks) . ')[-a-zA-Z0-9@:%._\\\\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\\\\+.~#?&\/=]*)(?! *<\/a>)/', "<a href=\"$0\"" . ($this->linkNewTab ? " target=\"_blank\"" : "") . ">$0</a>", $str); // auto URL
        }

        $str = preg_replace_callback('/\\\\([^&]|&lt;|&gt;)/', function ($match)
        {
            if ($match[1] == '\\' || $match[1] == '`' || $match[1] == '*' || $match[1] == '_' || $match[1] == '{' || $match[1] == '}' || $match[1] == '[' || $match[1] == ']' || $match[1] == '&lt;' || $match[1] == '&gt;' || $match[1] == '(' || $match[1] == ')' || $match[1] == '#' || $match[1] == '+' || $match[1] == '-' || $match[1] == '.' || $match[1] == '!' || $match[1] == '|' || $match[1] == '~' || $match[1] == '^')
            {
                return $match[1];
            }
            else
            {
                return '\\' . $match[1];
            }
        }, $str); // Remove backslash in front of escaped characters

        return $str;
    }
}
