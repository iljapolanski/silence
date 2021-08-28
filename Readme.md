### Silence command Setup
1. clone from repository
```git@github.com:iljapolanski/silence.git```
2. run composer install
3. run command with existing xml file and optional other options
```php bin/console silence:create-json --xmlPath="silence1.xml"```
4. the resulting json file can be found in ```{$APP}/data/jsonoutput/out.json``` 
5. you can specify any other path for xml source file from a directory other than 
```{$APP}/data/xmlsource/```, then you have to provide full path in the system.
6. there are other optional options for setting min chapter silence timeout, 
minimal part timeout, minimal chapter unsplitted duration.
--chapterTimout, default 2 seconds,
--partTimout, default 0.5 seconds,
--maximumChapterDuration, default 180 seconds, or 3 minutes.
All these opttions are to be set in float seconds.

