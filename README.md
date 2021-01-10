# Grav Editable with ContentTools Plugin

The **Editable with ContentTools** Plugin is for [Grav CMS](http://github.com/getgrav/grav).

> Version 1.6.1 has successfully been tested with Grav 1.7.0-rc.20

The plugin allows authors to edit page content in the frontend using the WYSIWYG editor [ContentTools](http://getcontenttools.com/) and save it as Markdown.



> **Important:** The plugin works with plain Markdown content from regular Grav pages. In other words, it can not work with content which is processed or inserted dynamically via Twig, shortcodes, through Javascript, by means of a plugin or any other way.   
>
> The plugin doesn't work for Modular Pages since these are assembled into a 'parent' page via Twig which is a one way process.   
> This may limit its use case depending on your requirements.


## Demo

Before installing feel free to try the [demo](https://festeto.net/demo-grav-plugin-editable-contenttools/).

BTW Please visit the [ContentTools](http://getcontenttools.com/) website for tips on using the ContentTools editor, like holding down the Shift key for about 3 seconds to see what regions on the page are editable (tip!).


***

![Screenshot of Grav with the ContentTools editor in use](https://user-images.githubusercontent.com/9297677/52519784-004ce500-2c61-11e9-9645-a3191c941ac2.png)

***

## Installation

Typically the plugin should be installed via [GPM](http://learn.getgrav.org/advanced/grav-gpm) (Grav Package Manager):

```
$ bin/gpm install editable-contenttools
```

Or you can manualy install it by [downloading](https://github.com/bleutzinn/grav-plugin-editable-contenttools/archive/master.zip) the plugin as a zip file. Copy the zip file to your `/user/plugins` directory, unzip it there and rename the folder to `editable-contenttools`.

Before configuring this plugin, you should copy the `user/plugins/editable-contenttool/editable-contenttools.yaml` to `user/config/plugins/editable-contenttools.yaml`.

Make configuration changes to that copy so your changes will be kept when installing a new version of the plugin.

Alternatively the plugin can be installed via the [Admin Plugin](http://learn.getgrav.org/admin-panel/plugins). When using the Admin Plugin there is no need to copy the configuration files manually.



## Configuration

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
git-sync: false
git-sync-mode: foreground
```

Setting `enabled` to `true` enables or activates the plugin.

When `git-sync` is `true` and the Git Sync plugin is installed and enabled, every save action triggers a Git Sync synchronisation.

The `git-sync-mode` setting defaults to `foreground` where the plugin halts until the synchronisation is finished before control is handed back to the user. When set to `background` the sync is unobtrusive to the user.

> Note: background syncing on Linux systems is not guaranteed to work and has currently not been tested on a Windows server. Feedback is welcome!

<a name="limitedusecase"></a>

## Possibly limited use case

This plugin works on plain simple Markdown only. This limits the use case but is a consequence of the conversion of Markdown to HTML and back again.

Just to set expectations right please note that this plugin **will not work** on a page or on content which:

* is processed by a Twig template, for example modulars
* is injected by plugins, for example the Page Inject Plugin
* is altered in the browser through Javascript

and **will corrupt** special Grav Markdown tags such as:

```
![Sample Image](sample-image.jpg?lightbox=600,400&resize=200,200)
```
#### Mitigating problems

There are some simple rules to keep on the safe side:

* Keep images and other shortcodes outside your editable regions
* Create small editable regions; it does not matter how many
* Experiment and test to make sure it works for you and you don't lose valuable content
* When you notice a difference in markup of the page with the ContentTools pen icon present and without keep that part of the page outside of your editable regions, it means trouble is ahead ;)



## The shortcode [editable]

Page content that may be edited in the frontend should be marked by using the shortcode `[editable]`.

For example:

```
[editable]
# Chapter 1

Once upon a time ...
[/editable]
```

A page may contain any number of such editable regions.

### Shortcode name parameter (optional)

For the ContentTools editor to work every editable region in a page must be uniquely identified by a 'name' parameter. Optionally you can give each shortcode a name which must be unique within the page.

The name parameter is optional. When non or an empty shortcode name parameter is present the plugin assigns names like "region-x" where x is a sequential increasing number.

For example:

``````
[editable name="region-0"]
Once upon a time ...
[/editable]
``````

To be able to save changed content back into the right regions the names need to be present in the page content and are therefor automatically saved in the page.



## User Permissions

### Frontend Users

To enable users to edit content in the frontend they must be able to login. 

Access to the frontend requires a seperate login as documented in the [Grav Login plugin](https://github.com/getgrav/grav-plugin-login) or the [Private Grav Plugin](https://github.com/Diyzzuf/grav-plugin-private).

To edit a page a frontend user must have the permission `site.editable`. Add the required authorization to each user in the user's account file:

```
access:
  site:
    login: 'true'
    editable: 'true'
```

### Backend Users

By default Grav separates backend (Admin) and frontend users into separate sessions.   
Allowing backend users to edit pages in the frontend requires the Grav option `session.split` to be set to `false` (in `system.yaml` or in the Admin panel).

A backend or Admin user must have the permission `admin.super` or `admin.pages` to be allowed to edit a page.



## Credits

Thanks go to:

- The Grav [Core Team](https://getgrav.org/about) for creating Grav
- getme for creating [ContentTools](https://github.com/GetmeUK/ContentTools)
- Dom Christie for creating [Turndown](https://github.com/domchristie/turndown)
- Paul Hibbits of [Hibbits Design](https://hibbittsdesign.org/) for testing and feedback
- Everyone on the [Grav Forum](https://getgrav.org/forum) for supporting Grav



## To Do's

<a name="todos"></a>

[ ] Add file upload   
[ ] Add superscript and subscript tools   
[ ] Think about handling images   

BTW all the above to do's require custom ContentTools tools and I don't have a clue how to create one...

