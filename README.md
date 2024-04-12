# Irs CMS Setup

This module simplifies management of CMS content during development and release. It allows to define 
CMS blocks, pages and media assets as a part of a module and deploy it on `bin/magento setup:upgrade`
or import with `bin/magento setup:cms:import` command.

The module also allows export of CMS blocks and pages with used media assets in a format that can be used 
later for import.

## Content Export

The export is implemented as an action in Content > Elements > Pages and Content > Elements > Blocks
grids in Admin Panel. To export all pages or blocks select Export action from Actions dropdown menu 
of the grid. To export particular pages or blocks select them with a checkbox in the first column of 
the grid then select Export action from the dropdown. 

Exported content will be packed into ZIP archive that may contain following directories:

  - blocks
  - pages
  - media

Blocks and pages will contain HTML files with special header. One file per page or block. Media folder
will contain media assets used in the blocks and the pages. If CMS blocks are injected into exported pages
with widgets they will be also added to the archive. This content can be deployed to other environments. 

## Content Update Strategies

Since the module updates an environment that can be shared between many users there's a risk that it may 
overwrite something that it should not. The content update strategies were implemented to deal with it.

This module introduces Allow Overwrite attribute to CMS blocks & pages. It's set to Yes on initial content 
import, and it set to No on block or page saving in Admin Panel. The strategies define what to do 
if a block or a page from the content archive already exists in database, and it does not allow overwriting.

There are three content update strategies:

  - *Error* is a default strategy. It throws error and stops import.
  - *Skip* strategy omits CMS blocks or pages that's not allowed to update.
  - *Force* strategy overwrites any block and any page.

The default strategy can be defined in the configuration with:

```shell
bin/magento config:set dev/irs_cmssetup/update_strategy <error|skip|force>
```

command or in Admin Panel > Stores > Configuration > Advanced > Developer > CMS Setup > Content Update Strategy
options. This option is available in Admin Panel only in developer mode. 

It's supposed that Force strategy will be used on staging environments when data modifications in Admin Panel 
are not expected. Skip and Error strategies can be used on environments where a client may modify CMS content.
Environment-wide strategy can be defined in `app/etc/env.php` with following command:

```shell
bin/magento config:set --lock-env dev/irs_cmssetup/update_strategy <error|skip|force>
```

## Content Import

Created content archive can be imported into the environment with `bin/magento setup:cms:import` command.
It accepts content archive or content directory as an argument. By default, the command reports only on 
errors. To see information about execution progress, `-vv` or `-vvv` verbosity level options can be used.

The import command supports few options that allow override current content deployment strategy:

  - `--force` forces update of all blocks & pages that cannot be updated due to selected content update strategy.
  - `--skip` skips all blocks & pages that cannot be updated due to selected strategy without an error.
  - `--dry-run` executes an update without environment modifications.

## Content Deployment

The module supports deployment of CMS content with `Irs\CmsSetup\Setup\ContentUpdatePatch.` 
This patch should be extended in some module that contains content that to be deployed.
Minimal structure of the module should be following:

```
app/code/Foo/Bar/
  content/
    blocks/
    pages/
    media/
  etc/
     module.xml
  Setup/
    Patch/
      Data/
        ContentUpdateV1.php
  registration.php
```

`Foo\Bar\Setup\Patch\Data\ContentUpdateV1` data patch should extend `Irs\CmsSetup\Setup\ContentUpdatePatch.`
Name of the patch can be arbitrary. It will deploy CMS blocks, pages and media content from `content/` directory.
`blocks/` and `pages/` should contain files with CMS blocks and pages correspondingly. The format of the files
is described in details in Cookbook chapter. Also blocks and pages in this format can be exported as it was 
described in Content Export chapter. Files from `media/` directory of the module will be copied to `pub/media/` 
directory of project maintaining directory structure.

The content update patch can override default strategy with `strategy` property.

```php
class ContentUpdateV1 extends ContentUpdatePatch
{
    protected UpdateStrategy $strategy = UpdateStrategy::Skip;
}
```

## Cookbook

### How to Deploy CMS Block?

Put block file into `content/blocks` directory of a module:

````html
id: promo-block-drills-drivers
title: Promo Block - Category - Drills Drivers
----
<style>
    ...
</style>
<h3>Everyday low prices on quality brands</h3>
<p>
    Browse our huge range of handheld power drills, cordless drills and drill drivers from big name 
    brands like <a href="/brand/makita">Makita</a>, <a href="/brand/stanley-fatmax">Stanley FatMax</a>, 
    <a href="/brand/dewalt">DeWalt</a>, <a href="/brands/bosch">Bosch</a>, 
    <a href="/brand/rockwell">Rockwell</a> and more. Don’t get caught out without a cordless drill -
    our everyday low prices mean bigger savings on the best products for your projects.
</p>
````

The file name and extension can be arbitrary, but it makes sense to use `html` extension to allow syntax 
highlighting. The block file consists of two parts: headers and a body. The parts are divided with a line 
that contains only `----` characters. Each header consists of a name and value divided with `:` character.
Header names are case-insensitive. Leading and trailing characters are removed from header names and values.
Thus, following header is equal to the previous file:

````html
ID    : promo-block-drills-drivers
Title : Promo Block - Category - Drills Drivers
````

Then create data patch in the module that inherits abstract class `Irs\CmsSetup\Setup\ContentUpdatePatch:`

````php
namespace Foo\Bar\Setup\Patch\Data;

class UpdateContentV1 extends \Irs\CmsSetup\Setup\ContentUpdatePatch
{}
````

Run `bin/magento setup:upgrade.` If you need to re-deploy blocks after modifications files in `content/blocks`
directory you need to rename the data patch to some new name, for example `UpdateContentV2` and run
`bin/magento setup:upgrade` again.

Blocks in `content/blocks` directory can be organized in arbitrary subdirectories of any level of nesting.

## How to Deploy Disabled CMS block?

There is optional header `active` with two possible values: `yes` and `no`. Header's name and value are 
case-insensitive. It's set to `yes` by default.

````html
Id:     promo-block-drills-drivers
Title:  Promo Block - Category - Drills Drivers
Active: No
----
<h3>Everyday low prices on quality brands</h3>
...
````

## How to Deploy CMS Block to Particular Store?

By default, blocks are added for all store views. It can be enabled only for particular stores with `stores` header.
This header contains comma separated list of store codes:

````html
id:     promo-block-drills-drivers
TITLE:  Promo Block - Category - Drills Drivers
Stores: default, eu_store, au_store
----
<h3>Everyday low prices on quality brands</h3>
...
````

Since we can have few blocks with the same identifier a block for update is selected taking stores into account.
Following algorithm is used:

1. Select all blocks with given identifier.
2. Find a block among them with the same set of stores.
3. If no such block has found then create new one.

It can lead to problem when you try to modify stores of already created block. For example, you have created 
following block in the database with `content/blocks/Drills Drivers.html` file.

````html
Id:     promo-block-drills-drivers
Title:  Promo Block - Category - Drills Drivers
Stores: eu_store, au_store
----
<h3>Everyday low prices on quality brands</h3>
...
````

Then you're removing `eu_store` from the file to allow this block for `au_store` only:

````html
Id:     promo-block-drills-drivers
Title:  Promo Block - Category - Drills Drivers
Stores: au_store
----
<h3>Everyday low prices on quality brands</h3>
...
````

and run `bin/magento setup:upgrade.` At this point you will receive an error because it will try to create 
new block with identifier `promo-block-drills-drivers` allowed in `au_store` but there is already a block with
the same identifier in `eu_store` and `au_store` stores.

To resolve this problem you need to remove the block with data patch first:

````php
namespace Foo\Bar\Setup\Patch\Data;

class UpdateMediaV3 extends \Irs\CmsSetup\Setup\ContentUpdatePatch
{
    public function apply()
    {
        $this->deleteBlock('promo-block-drills-drivers', ['eu_store', 'au_store']);
        
        parent::apply();
    }
}
````

## How to Deploy Two CMS Blocks with the Same Identifier in Different Stores?

Create to files with different names, for example:

1. `content/blocks/Drills Drivers (Europe).html`
2. `content/blocks/Drills Drivers (Australia).html`

Both files should have the same `id` header but different store codes in `stores` header:

````html
Id:     promo-block-drills-drivers
Title:  Promo Block - Category - Drills Drivers - Australia
Stores: au_store
----
<h3>Everyday low prices on quality brands</h3>
...
````

````html
Id:     promo-block-drills-drivers
Title:  Promo Block - Category - Drills Drivers - Europe
Stores: eu_store
----
<h3>Everyday low prices on quality brands</h3>
...
````

## How to Deploy CMS Pages?

CMS pages are deployed the way similar to CMS blocks. You need to add page files into `content/pages` directory.
Create data patch the inherits `Irs\CmsSetup\Setup\ContentUpdatePatch` and run `bin/magento setup:upgrade.`
The same data patch can deploy CMS blocks and pages in the same time.

Page files have the same format as block files.

```html
id: 2017-calendar
title: 2017 CALENDAR
stores: gb_store
layout: 1column
content heading: 2017 CALENDAR
----
<div data-content-type="row" ...
```

Its support the same headers and few additional specific to CMS pages:

  - Content Heading
  - Layout
  - Meta Title
  - Meta Keywords
  - Meta Description

The same rules of loading and creation are applied to them. Already existing page can be deleted with following code:

````php
namespace Foo\Bar\Setup\Patch\Data;

class UpdateMediaV4 extends \Irs\CmsSetup\Setup\ContentUpdatePatch
{
    public function apply()
    {
        $this->deletePage('2017-calendar', ['gb_store']);
        
        parent::apply();
    }
}
````
