**Eloqua Forms**

- Contributors: Cody Boyte, Ryan Ghods
- Tags: forms, eloqua
- Requires at least: 3.0.1
- Tested up to: 4.24
- Stable tag: 1.0
- License: GPLv2 or later
- License URI: http://www.gnu.org/licenses/gpl-2.0.html


Eloqua Forms is a simple plugin that imports all forms from an Eloqua instance into a custom Form post type. 

**Description**

This is a fairly simple plugin. It adds a custom post type, Eloqua Forms, and has a function to import the forms
from the Eloqua API. 

The plugin also provides a form shortcode for simple injection of the forms. It can be used in any post, page or 
custom sidebar widget. It can also be used in connection with Advanced Custom Fields to create a simple
drop-down selector that injects forms into a page without needing shortcodes. 

Shortcode `[show_form]` attributes:
* post_id - Wordpress post ID of form post
* eloqua_form_id - Eloqua form ID
* title - the title above the form
* redirect_to - where the form should be redirected to if using redirectURL fields (requires http://)

It currently utilizes login credentials hardcoded in the plugin itself, using Eloqua Basic Authentication. This
will be changed in future iterations to use the OAuth2 protocol.  

**Installation**

1. Edit line 41 of eloqua-forms.php and update with your own credentials
1. Zip all files into a single directory called wp-eloqua-forms
1. Upload `wp-eloqua-forms` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use shortcode wherever you'd like forms or integrate with Advanced Custom Fields to inject forms wherever you'd like

**Changelog**

= 1.0 =
* Created initial plugin using Basic Authentication
* Includes shortcode and custom javascript injections

**Credit**

The plugin was variously written and edited at [Axial](https://www.axial.net/) and [Jornaya](https://www.jornaya.com/).
