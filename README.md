# language-selector
Language selector feature for wordpress MultilingualPress plugin

## Purpose
This feature extends the Wordpress MultilingualPress plugin (https://wordpress.org/plugins/multilingual-press/) by providing a language selector which will allow the user to navigate between the different languages of the website. If the current page/post/category/tag is translated in the other languages then the language selector will redirect the user to the translated page/post/category/tag. If the content has not been translated the user will be redirected to the homepage.

## Installation
Copy the files for the inc and assets folders into the inc and assets folder of your MultilingualPress plugin directory.

## Configuration
Go to your MultilingualPress settings page, enable the Language Selector and save changes. Now on the same page a dedicated Language Selector Settings box should be visible where you can choose which sites you want to apear in the language selector. Check the one you want to apear and save changes.

## Integration in a theme
To make the language selector visible by the user you need to insert the following code in your wordpress theme (choose the place according to where you want the language selector do be displayed):

  "<?php do_action( 'mlp_language_selector' ); ?>"
  
## Costomizing language selector
You can customize the language selector by editing the .css files included in assets/css.
