#!/bin/bash

# automaton.sh - automate getting data
clear

# get core Wikipedia data and Polls.csv data
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

# run with nohup ./automaton.sh &-    later to run in background