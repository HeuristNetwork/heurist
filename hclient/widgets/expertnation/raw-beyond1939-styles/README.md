# Beyond 1939 Website

Stylings for *Beyond 1939*, the latest phase of Prof Julia Horne's long-running investigation into the military service of people associated with the University of Sydney.

The repository contains two main directories:

* `scss/`, where the raw scss files are contained for the project's stylings.
* `php/`, where the project's custom PHP template is contained. The custom template provides the basic layout of the site in the Heurist CMS.

To develop the css, run the 'dev' script to compile the scss to css live. The live file will appear in the `scss/dev`. It will be served from localhost on PORT 8080.

To develop the PHP, you will need access to the Heurist server where *Beyond 1939* is hosted. The php template can be uploaded into the HEURIST directory as required.

To build the css file for the website, you need to have [sass](https://sass-lang.com/dart-sass) installed on your machine. You can look in the `build.sh` script to see how to call sass to transpile the scss files into css for consumpation by the browser.