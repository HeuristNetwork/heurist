
Directory:    /hclient/widgets/cms

Overview:   Example templates for the creation of new widgets

Notes:      (awaiting development 7/3/25)
    
    Widgets acting on a single record (e.g. record edit, record view); 
    Widgets acting on the current Resultset or a selection (filter selector, results view, tabular view, map view, custom report, network diagram, storymap, various export functions, merge records, recode functions);
    Functions acting independently (pe. Filter and facet builders, Preferences, CSV import, structure export, new database)

Updated:     07 March 2025

----------------------------------------------------------------------------------------------------------------

/**
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2025 Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @version     7.0
*/

Development plan for CMS and CEO


ISSUES:
Relative path to images becomes wrong if it uses short url like https://heurist.huma-num.fr/Corpus_Sathma/web/
It is finally corrected (for user), but for googlebot it is wrong: https://heurist.huma-num.fr/Corpus_Sathma/web/?db=Corpus_Sathma&file=f43e9333434b584bdb463b63a1444b8560909c0d&fancybox=1 
Solution. Replace links to absolute on time of page generation on server
Set correct lang in <html lang=”en”> on page generation
Prevent name database with reserver words:  heurist*, h6-*, startup, databases, help, documentation,website|web|tpl|hml|view|edit|adm,api
Add rewrite rule: MyDatabase/file/[ulf_ObfuscationID]


CMS
1. editCMS2.js - to CmsEditor class (last turn)


2. hLayoutMgr.js - to core/HLayoutMgr class (replace old layout.js)
        save cms content in new format: json configuration in header + html content (to avoid encoding/escaping issues)
        on init it a) loads html b) applies styles c) inits widgets
        
3. websiteRecord.php  to CmsExectue.php  (invocation via FrontController.php?)
        a) finds home page
        b) loads values from home record: for header and footer elements (title, logos, banner, languages)
        c) includes custom css and scripts
        d) main menu content, generates as ul/li, all links are explicit/crawlable 
        e) loads/includes template file
                     f)    loads content of particular page
        g) outputs either to client or into html file (to /generated-web)
        h) generates sitemap
        
4. websiteScriptAndStyles.php  split to CmsScripts.php - include required scripts and styles and  CmsScripts.js  - class to manage/interact page (load pages, init widgets, fix relative links) 
                                  
5. ~10~20 sample templates
                                  
 CEO


1) +Sitemap for heurist (main) and published websites        
    a) use Google API to submit
    +b) include into robots:    Sitemap: https://heurist.huma-num.fr/main_sitemap.xml
2) +META noindex, nofollow for admin ui
3) +Disallow in robots.txt all folders except  /(root), /startup, /databases
4) +Does not include to index recordView for particular records


5) Create published/generated version of websites. Use name of page instead of record id. Use dashes for spaces and utf8 encoding


    https://heurist.huma-num.fr/MyDatabase/web/92/page-name.html - generated version form HEURIST_FILESTORE/MyDatabase/generated-web
    https://heurist.huma-num.fr/MyDatabase/web/92/342 - dynamic version


6) OPTIONAL: Country-specific subdirectory with gTLD (instead of param) 


   https://heurist.huma-num.fr/MyDatabase/web/92/342/fre
        
7) !!!! Make all links crawlable

        
--------


1. startup/ Menu in header: about, startup, databases, websites
2. database index (standard header and css)  with search and featured/selected
3. database pages (standard header and css)
4. minified core scripts (modules)
5. loads widgets code on demand
      
        
        

// refer CMS: WordPress, Wix, Blogger        
        
// Turn on output buffering
ob_start();
echo "Foo";                          
//  Return the contents of the output buffer
$htmlStr = ob_get_contents();
// Clean (erase) the output buffer and turn off output buffering
ob_end_clean(); 
// Write final string to file
file_put_contents($fileName, $htmlStr);

