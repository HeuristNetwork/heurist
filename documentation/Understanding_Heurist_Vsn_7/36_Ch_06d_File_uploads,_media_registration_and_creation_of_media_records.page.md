# Ch 06d: File uploads, media registration and creation of media records

*Documentation by ChatGPT Sol 5 lite 6/10/26 with some additions. Content not yet systematically verified.*

#### **Populate &gt; Media files**

**T**hese menu items provides tools for uploading files, registering externally stored files and creating database records to describe them.

[![image.png](https://docs.heuristref.net/uploads/images/gallery/2026-10/scaled-1680-/TLrimage.png)](https://docs.heuristref.net/uploads/images/gallery/2026-10/TLrimage.png)

<p class="callout info">Heurist distinguishes between a **registered file**, which can be attached to a record through a File field, and a **Digital Media record**, which describes the file and can be searched, classified and linked to other records.</p>

**Manage files**

Opens the file manager, listing files uploaded to the database and registered references to external resources. It allows you to inspect and preview files, edit their descriptions, register additional files or external URLs, and remove unwanted entries. File references can also be exported as CSV for use in subsequent imports.

Use this to manage the database’s collection of attachments. Registering a file does not, by itself, create a Digital Media record describing it.

Take care when deleting files: records that use them may lose access to their attachments.

**Upload files from local**

Uploads files from your computer into the database’s file storage and registers them with Heurist. These may include images, PDFs, audio, video and other permitted file types. Thumbnails are generated where supported.

This is useful for uploading a batch of files before attaching them to existing records or creating Digital Media records.

Uploading makes the files available to the database; it does not automatically create descriptive records for them. File size and type restrictions depend on the server configuration. Very large files or large collections may require transfer by a server administrator, followed by **Register external transfers**.

<p class="callout info">Heurist is designed as a working data system not a repository and does not stream audio and video, necessitating a full download of audio and video. Streamable files are best loaded on a streaming server such as YouTube (ad supported) or PeerTube, or one provided by your local institutions. Large scans are best placed in a repository such as Nakala or Zenodo and referenced as remote files.</p>

<p class="callout warning">Typically Heurist is configured in Apache with a 30MByte limit on PHP file uploads to avoid the upload of huge video files or very large TIF scans, which could consume more space than dozens or hundreds of individual databases. Although these will not affect the performance of the database, they may not open on a web browser and/or take an inordinately long time to load, making them next to useless.</p>

**Upload files from URLs**

Registers a batch of files using a list of URLs, optionally accompanied by descriptions. You can paste the list into the dialogue or upload a delimited text file. A recommended format is a CSV with the column headings <span style="background-color: color(srgb 0.0337662 0.0337662 0.0337662 / 0.0905882);">URL,Description</span>.

After analysing the list, identify the URL and description columns and choose whether to:

- **Download the files:** Heurist retrieves them and stores local copies in the database’s file storage.
- **Register external references:** Heurist stores their URLs and accesses the files at their existing locations.

Use direct file URLs wherever possible, rather than links to web pages containing the files.

Local copies remain available independently of the original provider. External references depend on the provider keeping the files accessible at the registered URLs. This operation registers files; descriptive Digital Media records can be created separately where appropriate.

**Register external transfers**

Scans the database’s configured media folders for files placed there outside the normal Heurist upload process—for example, files transferred directly by SFTP by a server administrator.

It adds previously unregistered files to the file manager and generates thumbnails where supported. Files already indexed are left unchanged. The folders and file extensions to scan are controlled by the database’s media settings.

Use this after a direct server transfer, particularly for collections too large to upload conveniently through the browser.

Here, “external transfers” means files transferred into the database’s server storage by another method. It does not mean registering remote URLs. The operation registers files but does not create Digital Media records.

**Create records from files**

Creates **Digital Media records** for files in the configured media folders, with each record linked to its corresponding file. These records provide a place to store descriptive information, classifications and connections to other database records.

The process scans the configured folders and their descendants and uses file metadata and associated XML manifests where available. Files already represented by a corresponding Digital Media record are not given another record by this process; existing Digital Media records are left unaffected.

Use this when the files themselves are research objects—for example, a collection of photographs that needs individual descriptions and links to people, places or objects.

Check the folders and permitted extensions before proceeding: scanning a large collection can create many records. The database also needs the appropriate Digital Media record type and fields, identified by their Concept IDs.

<p class="callout info">Please see the chapter on IIIF manifests and annotations for **Process IIIF manifest**</p>