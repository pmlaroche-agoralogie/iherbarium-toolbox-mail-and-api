#!/bin/bash
echo "Run iHerbarium Mail System"

# Settings.
echo -e "\nSettings:"

date=`date +%Y.%m.%d_%T`
echo "Date = $date"

rootDir="/home/expert1/htdocs/boiteauxlettres/"
echo "rootDir = $rootDir"

runLogsDir="runLogs" # NO SLASH!
echo "runLogsDir = $runLogsDir"

logFilename="run_$date.html"
echo "logFilename = $logFilename"

url="http://boiteauxlettres.iherbarium.fr/boiteauxlettres/main.php"
echo "url = $url"

logUrl="http://boiteauxlettres.iherbarium.fr/boiteauxlettres/$runLogsDir/$logFilename"
echo "logUrl = $logUrl"

# Prepare commands.
curlCommand="curl $url -o $rootDir/$runLogsDir/$logFilename"

# Echo commands.
echo -e "\nCommands:"
echo "$curlCommand"

# Execute commands.
echo -e "\nExecuting...\n"
$curlCommand

# Done.
echo -e "\nDone!"

# Log's URL.
echo -e "\nLog's URL: $logUrl"