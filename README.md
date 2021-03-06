Cmacc-Mediawiki
===============

The Mediawiki extension for the Cmaac data model.  See beta.commonaccord.org.

How to use it
===============

The Mediawiki extension for the Cmaac data model.  See beta.commonaccord.org.

@hazardj thinks the only things you need to do to get this working in Mediawiki are the following.  LMK.


1. Install MW
2. Add the following into your LocalSettings.php

require_once( "$IP/extensions/Binder/binder.php" );


3. In the extentions directory, create the Binder directory.  Add this file as binder.php:

Many thanks to Andrew.  Some fiddling by @hazardj under his initials - JGH. 



What's this do?
================
Nested responses:

It adds to MW a function that is like transclusion:
  A list of pages (Binder)
  each page can have one or more lists of key/values (Fields)
  Rendering the Binder starts with a default field ( {Model.Root} ) and looks for it by looking down the succession of lists.  
    If found in a list, the text of the value is substituted for the {Model.Root} in the text.
    If not found, then it looks for a page called Model.Root
      If found, then the text of the page is substituted for the {Model.Root} in the text.
      If not found, then no substitution is made.
  Repeat for each {Token} found in the replacement text.
  
There is a variation (Render with Edit Links) that sort of marks the replacement by putting in a <a href="TokenName">*</a> just before.  But totally improvised by @hazardj and buggy.  A really impressive version of this was done for the Xwiki platform by Ludovic Dubost.

What it doesn't do:

* Recursion, prefixing, include other URLs, wrap the spans with metadata to allow easy understanding and editing.  


Next Level:

It is used in http://beta.commonaccord.org to codify legal documents.

Next Level:
It is an example of the use of this Lisp-like function.  See http://beta.commonaccord.org/wiki/About:TechnicalEnablement

Top Level:
An iterative semantic web.
