Messify
=========

Messify is PHP class for communicating with messify.ru service API. It helps organize caching optimized with messify.ru scripts and stores CSS and JavaScript file collection in you webapp or website.

It is useful if you website has too much CSS and JavaScript files on every page and you want to reduce amount of external files includes and compress all or some of them.

Compressing CSS is available with [YUI] compiler, [CssMin].

Compressing JavaScript is available with [GCC] Google Closure Compiler (PRO only), [YUI] compiler, [JsMin].

Basic usage
=========

Clone class
--
```
git clone https://github.com/magwai/messify.git
```

Include class and create instance
--

```
include 'messify.php';
$messify = new messify();
```

Add CSS and JavaScript files
--

```
$messify
  ->add('js', 'http://code.jquery.com/jquery-latest.js')
  ->add('js', '$("#jquery_test").html("Hello World");', array(
    'inline' => true,
    'render_inline' => false
  ))
  ->add('css', 'http://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.css')
  ->add('css', 'body{background:#cccccc;}', array(
    'inline' => true,
    'render_inline' => false
  ));
```

Output result
--
```
<!DOCTYPE html>
<html>
  <head>
    <meta charset=utf-8>
    <?php echo $messify->render('css') ?>
  </head>
  <body>
    <div id="jquery_test"></div>
    <?php echo $messify->render('js') ?>
  </body>
</html>
```
Result example
--
```
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <link href="/messify/css/eb354.css" media="all" rel="stylesheet" type="text/css">
  </head>
  <body>
    <div id="jquery_test">Hello World</div>
	<script type="text/javascript" src="/messify/js/1843.js"></script>
  </body>
</html>
```
All files was compressed via messify.ru and then merged in one file. So, you have only one CSS and one JavaScript files in you web page, loaded from cache on your domain. Messify class dealing with conditional HTML rules for specific files. You can disable compressing and merging for specific files or disable remote files downloading.

More info
=========
Documentation for class: [messify.ru/page/doc]

Documentation for messify.ru API: [messify.ru/page/api]

Demo: [Working demo]

[messify.ru/page/doc]:https://messify.ru/page/doc
[messify.ru/page/api]:https://messify.ru/page/api
[Working demo]:https://github.com/magwai/messify/blob/master/demo.php
[messify.ru]:https://messify.ru
[YUI]:http://yui.github.io/yuicompressor
[GCC]:https://github.com/google/closure-compiler
[CssMin]:http://code.google.com/p/cssmin
[JsMin]:https://github.com/rgrove/jsmin-php