# HEURIST

Core development repository, Vsn 6 (2021 - ), Vsn 5 (2018 - 2020), Vsn 4 (2014-2017). Vsn 1 - 3 on Sourceforge (2005 - 2013)

**See below the intro for information on code, licensing, installating, contributing and issue tracking**

Heurist is a mature web-based data management infrastructure that is specifically tailored to the needs of Humanities researchers. Heurist allows researchers to design, create, manage, analyse and publish their own richly-structured database(s) through a simple web interface, without the need for programmers or consultants. A complete application can be built in as little as half a day and complex databases in under a week. Database structure can be modified incrementally on live databases allowing them to evolve with project needs. The project runs free services for researchers independent of institution (through the University of Sydney in Australia and the Huma-Num eResearch service in France), which frees the researcher from the need to manage servers, backups and upgrades.

## Version 6 Interface


Advanced features include foreign keys and relationships seen and modified by the user as intuitive pointer fields, multi-level facet searches and network expansion rules built via a wizard, interactive maps and timelines, built-in CMS websites which embed saved searches, media and widgets from the database interface to publish searches linked to result lists, maps etc. A central index allows Heurist databases to import structural elements from any registered database to promote sharing of data models. Right-to-left scripts and Asian characters are fully supported. There is provision to translate the interface and for cross-database searching although we have not had the resources to do this.

![Main Interface Vsn 6](/documentation_and_templates/assets/main%20interface%20v6.jpg "Main Interface Vsn 6")

## Research database workflow

Heurist has been developed at the University of Sydney since 2005, based on years of prior work, and is used by dozens of projects, particularly in Australia, France, Germany and the USA. These projects span the fields of history, archaeology, art history, geography, and literature. A selection of projects can be found here.
Heurist is in active development with more than 1000 commits/year since moving to gitHub in 2014. Version 6 was released in February 2021 and represents a complete redesign of the menu system and overall appearance in collaboration with a professional UX designer (Brant Trim, Serata Digital, Canberra) but is fully backward compatible with databases developed in version 5.
We transferred the help system from help builder software to a Heurist database in Nov 2020, which now generates the Heurist Help System (also accessible within Heurist). This is now being updated to reflect version 6 (work-in-progress 2021). Issue tracking is also handled by a Heurist database. 

![Model and Build](/documentation_and_templates/assets/model%20and%20build.jpg "Model and Build")

## Heurist Projects

Heurist has a built-in capability to generate data-driven interactive websites. The CMS website pages are stored and edited directly in the database and can render media, searches, maps, blog pages etc. using the data. Heurist hosts dozens of research websites around the world, several of which are displayed below. You can see [featured projects](https://heuristnetwork.org/featured-projects) and [search our projects database](https://heuristnetwork.org/projects-search) at our website.

![Projects Collage](/documentation_and_templates/assets/project_thumbnail_collage.png "Projects Collage")


## Contributing

We very much welcome collaboration and invite contributions to the development of Heurist. We will be delighted to exchange design ideas and our development roadmap with developers, researchers, documenters and trainers who would be interested in contributing to the system or developing specific extensions or training programs for their own use. Please feel free to add suggestions to our job tracking system (see below) or email support@heuristnetwork.org.

In 2022, we are undergoing significant refactoring and code documentation, and have formed a documentation group of power users and developers. To get started with the source code, you can view some of the items in the [documentation and templates](/documentation_and_templates/) directory. Most of the server-side code is in the `hsapi` directory. Most of the client code is in the `hclient` directory.

## Source code & licensing

The gitHub repository for Heurist source code is freely available under the standard GNU GPL 3 license.

The main branch is `h6-dev`. This is occasionally merged with `master` for archiving purposes.

## Issues and feature requests

Please note, we do not use gitHub issues. Please visit our [job tracking system](https://HeuristRef.net/heurist/?db=Heurist_Job_Tracker&website) developed in Heurist. You can raise an issue or request a feature there: first click the Add Job link at top left, then use login guest + guest (or request a personal login via the link on the login page). We are planning to integrate the job tracking system with Github by the end of 2022.

Issues and feature requests can also be submitted via the Issue tracker link in any Heurist database (Help > Bug report/feature request) - this also saves additional information about your use environment which may help resolve the issue.

## Online Help

Comprehensive help for the new version (6) is being built in a dedicated Heurist database, and is available [here](https://HeuristRef.net/heurist/?db=Heurist_Help_System&website).

## Installation

The distribution version at HeuristNetwork.org/installation is updated approximately once a month (more frequently in case of urgent bug fixes). Monthly updates generally contain numerous tweaks and bug fixes, while significant new features are added every two to three months. 
The installation and update scripts (see installation page) are very simple and have been proven over a decade - they should run on most Unix servers and have been designed specifically to restrict their activity to a single directory (/var/www/html/HEURIST) so that they do not mess with anything else installed on the server.
Heurist can also be installed on a Windows server - Systemik Solutions are working on documentation, please contact us for information.

## Feedback / questions

Please email support@HeuristNetwork.org

