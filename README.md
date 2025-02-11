# Adobe

This file contains Tim Frobisher's implementation of the following programming challenge for Adobe.

Programming Challenge:
Take a variable number of identically structured json records and de-duplicate the set.
An example file of records is given in the accompanying 'leads.json'.  Output should be same format, with dups reconciled according to the following rules:
1. The data from the newest date should be preferred.
2. Duplicate IDs count as dups. Duplicate emails count as dups. Both must be unique in our dataset. Duplicate values elsewhere do not count as dups.
3. If the dates are identical the data from the record provided last in the list should be preferred.
Simplifying assumption: the program can do everything in memory (don't worry about large files).
The application should also provide a log of changes including some representation of the source record, the output record and the individual field changes (value from and value to) for each field.
Please implement as a command line program.

Example format:

{"leads":[

{

"_id": "jkj238238jdsnfsj23",

"email": "foo@bar.com",

"firstName":  "John",

"lastName": "Smith",

"address": "123 Street St",

"entryDate": "2014-05-07T17:30:20+00:00"

},

{

"_id": "jkj238238jdsnfsj23",

"email": "bill@bar.com",

"firstName":  "John",

"lastName": "Smith",

"address": "888 Mayberry St",

"entryDate": "2014-05-07T17:33:20+00:00"

}]

}

This program should be called as follows:

php [--strict --case] de-duplicate.php inputFileName [outputFileName logFileName]

If the strict flag is set, the program will require each entry to have the exact six fields shown in

the above examples and no others. This aligns with the program spec "identically structured json records".

However, for the program to work all that is necessary is the entryDate field for sorting and the 

_id and email files for comparisons. There is no reason why other record differences should make the program

fail. For instance, a record without an address or a record with an added telephone number. Therefore, the

default is for strict to be off.

If the case flag is set then the program will not consider email addresses to be case sensitive. In other words,

timfrobisher@gmail.com will be different from TimFrobisher@gmail.com. Again, the default for case is off.

