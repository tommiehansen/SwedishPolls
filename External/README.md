External data
===

The data under `Data/` is not edited by humans but rather is generated by a collection of scripts that fetches data and outputs `csv` and `sqlite` files.
The goal is to enable faster and simpler updates of `../Data/Polls.csv` and enable updates under `Data/` to be performed automatically and periodically.

#### Difference to ../Data/Polls.csv
The merged data will follow Polls.csv where data is available from 'external source' and add 'NA' to the CSV-files where data does not exist or can not be calculated in some fashion. `NA` values are replaced with proper `NULL` values for the SQLite databases.

All data is sorted on
A: `collectPeriodTo DESC`
B: `Company DESC`

##### Generated files
`PHP/automaton.sh` generates the below files under `Data/`:

**Standard files**
- Polls.sqlite: SQLite version of `../Data/Polls.csv`
- Polls.csv: CSV-version of the SQLite version (with order `collectPeriodTo DESC, Company DESC`
- Polls.json: JSON-version of the above
- Wikipedia.sqlite: SQLite data fetched from:
  - https://en.wikipedia.org/wiki/Opinion_polling_for_the_Swedish_general_election,_2018
  - https://en.wikipedia.org/wiki/Opinion_polling_for_the_Swedish_general_election,_2014
- Wikipedia.csv: CSV-version of above
- Wikipedia.json: JSON-version of above

**Merged**

- Merged.sqlite: A combination of all data from `../Data/Polls.csv` and data from Wikipedia-pages:
  - https://en.wikipedia.org/wiki/Opinion_polling_for_the_Swedish_general_election,_2018
  - https://en.wikipedia.org/wiki/Opinion_polling_for_the_Swedish_general_election,_2014
- Merged.csv: CSV-version
- Merged.json: JSON-version
- Merged_last10.sqlite: A combination of `../Data/Polls.csv` and last ~10 entries from Wikipedia
- Merged_last10.csv: CSV-version
- Merged_last10.json: JSON-version

### Quality of the data
As high of a quality as one can expect from sources like Wikipedia meaning often ok but mistakes can be made by someone at some point in time.
For least amount of duplicates use any variation of `Merged_last10`.