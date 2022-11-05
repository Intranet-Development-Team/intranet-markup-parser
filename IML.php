<?php
class IMLparser
{
    private function block(string $str): string
    {
        $lines = explode("\n", $str);
        $openedLists = []; // array element properties: indent => [element => element]

        foreach ($lines as &$line)
        {
            if (preg_match('/^( *)((?:\d+\.)|-) +.+$/', $line, $match))
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
                if (preg_match('/^ *(#{1,6}(?!#)) *(.+?)$/', $line, $match))
                {
                    $line = '<h' . strlen($match[1]) . '>' . $match[2] . '</h' . strlen($match[1]) . '>';
                }
                else if (preg_match('/^ *&gt; *(.+?)?$/', $line, $match))
                {
                    $line = '<blockquote>' . $match[1] . '</blockquote>';
                }
                else if (preg_match('/^ *(=|-|#)\1{4,} *?$/', $line, $match))
                {
                    $line = '<hr>';
                }
                $line = preg_replace('/(?:^|\n)([^<>]+?)(?:$|\n)/', '<p>${1}</p>', $line);
            }
        }
        return implode("\n", $lines);
    }

    private function inline(string $str): string
    {
        $str = preg_replace('/\*\*\*(?=[^*]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\*\*\*/', "<strong><em>$1</em></strong>", $str);
        $str = preg_replace('/\*\*(?=[^*]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\*\*/', "<strong>$1</strong>", $str);
        $str = preg_replace('/\*(?=[^*]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\*/', "<em>$1</em>", $str);
        $str = preg_replace('/\_\_(?=[^_]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\_\_/', "<u>$1</u>", $str);
        $str = preg_replace('/\~\~(?=[^~]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\~\~/', "<s>$1</s>", $str);
        $str = preg_replace('/\=\=(?=[^=]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\=\=/', "<mark>$1</mark>", $str);
        $str = preg_replace('/\^\{(?=[^}]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\}/', "<sup>$1</sup>", $str);
        $str = preg_replace('/\_\{(?=[^}]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\}/', "<sub>$1</sub>", $str);
        $str = preg_replace('/\`\`\`(?=[^`]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\`\`\`/', "<code>$1</code>", $str);
        $str = preg_replace('/\!\[(?=[^\]]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\]\((https?:\/\/[^\)]+?)\)/', "<img src=\"$5\" alt=\"$1\">", $str);
        $str = preg_replace('/\[(?=[^\]]+)([^\n\<\>]*?(?:(\<(.+?)\>)[^\n\2\4]*?(\<\/\3\>)[^\n\<\>]*?)*?)\]\((https?:\/\/[^\)]+?)\)/', "<a target=\"_blank\" href=\"$5\">$1</a>", $str);
        $str = preg_replace('/(?<!\]\()(?>https?:\/\/[^\s]+)(?!\]\()/',"<a target=\"_blank\" href=\"$0\">$0</a>",$str);
        return $str;
    }

    public function text(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = preg_replace("/((\r(?!\n))|(\r\n))+/", "\n", $str); // Unify line breaks indicators and remove excess breaks
        $str = $this->block($str);
        $str = $this->inline($str);
        $str = preg_replace("/\n/", "", $str);
        return $str;
    }

    public function line(string $str): string
    {
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = $this->inline($str);
        return $str;
    }
}

if (isset($_POST["text"]))
{

    $start = hrtime(true);
    $obj = new IMLparser;
    $returned = $obj->text($_POST["text"]);
    echo (hrtime(true) - $start) / 1000000;;
}
?>
<html>
<form method="post">
    <textarea name="text" style="height:10em;"><?= $_POST["text"] ?? "" ?></textarea>
    <input type="submit">
</form>
<textarea style="height:10em;"><?= htmlentities($returned) ?? "" ?></textarea>
<div><?= $returned ?? "" ?></div>

</html>