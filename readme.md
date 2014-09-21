Messify
=========

Messify is PHP class for communicating with messify.ru service API. It helps organize caching optimized with messify.ru scripts and stores CSS and JavaScript file collection in you webapp or website.

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
    'inline' => true
  ))
  ->add('css', 'http://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.css')
  ->add('css', 'body{background:#cccccc;}', array(
    'inline' => true
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
More info
=========
Documentation for class: [messify.ru/page/doc]

Documentation for messify.ru API: [messify.ru/page/api]

[messify.ru/page/doc]:https://messify.ru/page/doc
[messify.ru/page/api]:https://messify.ru/page/api