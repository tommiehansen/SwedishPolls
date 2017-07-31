#!/bin/bash

# automaton.sh - automate getting data
# run with nohup ./automaton.sh &-    later to run in background

clear

# get core Wikipedia data and Polls.csv data
php polls.php
php wikipedia.php
php sqlite_format.php file=Polls.sqlite
php sqlite_format.php file=Wikipedia.sqlite

# standard merge + create csv
php merge.php
php sqlite_format.php file=Merged.sqlite format=csv file_out=Merged.csv

# create file with only X latest
php merge.php name=Merged_last10.sqlite strict=half-strict maxmerge=10
php sqlite_format.php file=Merged_last10.sqlite format=csv file_out=Merged_last10.csv