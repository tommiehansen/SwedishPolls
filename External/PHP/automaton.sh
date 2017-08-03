#!/bin/bash

# ==============================================================================
# 
# AUTOMATON
# automate getting data
#
# ==============================================================================

# VARs
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# cd into dir
# this so that we can run ie /mnt/c/my-super-dir-of-doom/superscript.sh
# else php won't find the relative files specified later
# else git won't know what we're committing/pushing (or we have to be very specific)

cd $DIR


# clear
clear

# get core Wikipedia and Polls.csv data
php polls.php
php wikipedia.php
php sqlite_format.php file=Polls.sqlite
php sqlite_format.php file=Polls.sqlite format=json file_out=Polls.json
php sqlite_format.php file=Wikipedia.sqlite
php sqlite_format.php file=Wikipedia.sqlite format=json file_out=Wikipedia.json


# standard merge + create csv
php merge.php automaton=true
php sqlite_format.php file=Merged.sqlite format=csv file_out=Merged.csv
php sqlite_format.php file=Merged.sqlite format=json file_out=Merged.json


# create file with only X latest
php merge.php name=Merged_last10.sqlite strict=half-strict maxmerge=10 automaton=true
php sqlite_format.php file=Merged_last10.sqlite format=csv file_out=Merged_last10.csv
php sqlite_format.php file=Merged_last10.sqlite format=json file_out=Merged_last10.json

# create reports
php odd_checker.php
php odd_checker.php name=Merged_last10.sqlite
php odd_checker.php name=Polls.sqlite


# start ssh agent (if not started)
# eval $(ssh-agent -s)

# Commit to Github and Push
git add -A && git commit -m "Automaton"
git push



# if cron/misc scheduler
# run with: nohup ./automaton.sh &-  
# to run in background