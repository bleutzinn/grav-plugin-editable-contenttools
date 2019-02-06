# Editable with ContentTools Plugin

The **Editable with ContentTools** Plugin is for [Grav CMS](http://github.com/getgrav/grav).   
The plugin allows authors to edit page content in the frontend using the WYSIWYG editor [ContentTools](http://getcontenttools.com/) and save it as Markdown.

> **Important:** The plugin works with regular pages. To put it the other way around, it can not work with content which is inserted via Twig, through Javascript, by a plugins or any other way of processing.

## Installation

Typically the plugin should be installed via [GPM](http://learn.getgrav.org/advanced/grav-gpm) (Grav Package Manager):

```
$ bin/gpm install editable-contenttools
```

Or you can manualy install it by [downloading](https://github.com/bleutzinn/grav-plugin-editable-contenttools/archive/master.zip) the plugin as a zip file. Copy the zip file to your `/user/plugins` directory, unzip it there and rename the folder to `editable-contenttools`.

Alternatively the plugin can be installed via the [Admin Plugin](http://learn.getgrav.org/admin-panel/plugins).

## Configuration

Before configuring this plugin, you should copy the `user/plugins/editable-contenttool/editable-contenttools.yaml` to `user/config/plugins/editable-contenttools.yaml`.

Make configuration changes to that copy so your changes will be kept when installing a new version of the plugin.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
git-sync: false
```

Setting `enabled` to `true` enables or activates the plugin.

When `git-sync` is `true` and the Git Sync plugin is installed and enabled, every save action triggers a Git Sync synchronisation.

## Usage

### Shortcode [editable/]

Page content that may be edited in the frontend is marked by using the shortcode `[editable/]`. The shortcode must wrap the Markdown content. Make sure the opening shortcode tag, the content and the closing shortcode tag must be on separate lines!

Each, so called, "region" must be given a name, for instance:

```
[editable name="chapter_1"]
Once upon a time ...
[/editable]
```

This way a page can have none, one or more editable regions. Region names in a page must be unique.

For more information on shortcodes see the documentation of the [Shortcode Core plugin](https://github.com/getgrav/grav-plugin-shortcode-core).

### User Permissions

#### Frontend Users

To enable users to edit content in the frontend they must be able to login. 

Access to the frontend requires a seperate login as documented in the [Grav Login plugin](https://github.com/getgrav/grav-plugin-login) or the [Private Grav Plugin](https://github.com/Diyzzuf/grav-plugin-private).

To edit a page a frontend user must have the permission `site.editable`. Add the required authorization to each user in the user's account file:

```
access:
  site:
    login: 'true'
    editable: 'true'
```

#### Backend Users

By default Grav separates backend (Admin) and frontend users into separate sessions.   
Allowing backend users to edit pages in the frontend requires the Grav option `session.split` to be set to `false` (in `system.yaml` or in the Admin panel).

A backend or Admin user must have the permission `admin.super` or `admin.pages` to be allowed to edit a page.


## Credits

Thanks go to Team Grav and everyone on the [Grav Forum](https://getgrav.org/forum) for creating and supporting Grav.

## To Do's
<a name="todos"></a>
   
[ ] Add file upload   
[ ] Add superscript and subscript tools   
[ ] Think about inserting images   
[ ] Think about replacing fixed template based images

BTW all the above to do's require custom ContentTools tools and I don't have a clue how to create one...


