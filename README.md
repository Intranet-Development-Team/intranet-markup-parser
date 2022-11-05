# Intranet Markup Parser
This Intranet Markup Parser parses human-readable IM code into HTML to be rendered in browser with PHP.

There will also be a JS implementation in the end.

## Introduction

You can use the parser like this:

```
$parser = new IFMparser;
echo $parser->text($input);
```

The above example can parse both block and inline elements, change it to `echo $parser->line($input);` for parsing inline elements only.

Please see the [Wiki page](https://github.com/Intranet-Development-Team/intranet-markup-parser/wiki) for more examples on the syntax of Intranet Markup and the usage of IMP.


## Security

IMP will automatically convert HTML into plain text by applying the `htmlspecialchars()` function. You can also define the whitelist of link by yourself using the `$parser->setAllowedLinks(...$links)` method.


