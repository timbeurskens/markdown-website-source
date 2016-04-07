# markdown-website-source
PHP source code for the markdown website

NOTE: The source code uses [Parsedown](https://github.com/erusev/parsedown) as a markdown processor.
Please include the Parsedown source into the project and point to this file in the settings.

The _options.json_ file should be located in the parent folder for this build.

## Example _options.json_ file
```json
{
  "parsedown_location": "/home/user/public_html/parsedown/Parsedown.php",
  "buildscript": "/home/user/public_html/build/buildscript.php",
  "temp_location": "/home/user/public_html/temp/",
  "dest_location": "/home/user/public_html/",
  "exclude": []
}
```
