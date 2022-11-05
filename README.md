# Intranet Markup Parser
This Intranet Markup Parser parses human-readable IM code into HTML to be rendered in browser with PHP.

## Introduction

You can use the parser in PHP like this:

```php
$parser = new IMP;
echo $parser->text($input);
```

The above example can parse both block and inline elements, you can also use the parser for only inline elements:

```php
$parser = new IMP;
echo $parser->line($input);
```

Please see the [Wiki page](https://github.com/Intranet-Development-Team/intranet-markup-parser/wiki) for the complete usage of IMP.


## Security

IMP will automatically convert HTML into plain text by applying the `htmlspecialchars()` function. You can also define the whitelist of link by yourself using the `setAllowedLinks()` method.