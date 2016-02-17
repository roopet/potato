# Potato

Converts Po files to CSV files and back to Po format.

## Usage

### Step 1: .po to .csv
Put all .po files into in/ folder and run potocsv, answer questions and resolve conflicts if any
```
$ php potato potocsv


    Po to Csv converter


Using CWD /home/kehet/potato/

Found following .po files:
/home/kehet/potato/in/en.po
/home/kehet/potato/in/se.po

Do you want to edit input files? [y/N]: n

Give output folder [/home/kehet/potato/out]:
loading en...
loading se...
merging po files...
writing csv...
done. output file is /home/kehet/potato/out/translations.csv
```

### Step 2: Send CSV to customer
Wait for translations (this subroutine may take long time)

### Step 3: .csv to .po
Remove all formatting customer may have added. Run csvtopo, answer questions and resolve conflicts if any
```
$ php potato csvtopo


    Csv to Po converter


Using CWD /home/kehet/potato/

Give input .csv file [/home/kehet/potato/in/translations.csv]:

Found following .po files:
/home/kehet/potato/in/en.po
/home/kehet/potato/in/se.po

Do you want to edit input files? [y/N]: N

Give output folder [/home/kehet/potato/out]:
loading en...
loading se...
reading from csv...
writing en...
writing se...
done
```

## Copyright
Potato is from po to csv to po translation file converter.
Copyright (C) 2016  Vividin Oy

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.