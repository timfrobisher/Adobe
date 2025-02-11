<?php

declare(strict_types = 1);

namespace Frobisher;
use \DateTimeInterface;
use \DateTime;

/** 
 * This file contains Tim Frobisher's implementation of the following programming challenge for Adobe.
 * 
 * Programming Challenge:
 * Take a variable number of identically structured json records and de-duplicate the set.
 * An example file of records is given in the accompanying 'leads.json'.  Output should be same format, with dups reconciled according to the following rules:
 * 1. The data from the newest date should be preferred.
 * 2. Duplicate IDs count as dups. Duplicate emails count as dups. Both must be unique in our dataset. Duplicate values elsewhere do not count as dups.
 * 3. If the dates are identical the data from the record provided last in the list should be preferred.
 * Simplifying assumption: the program can do everything in memory (don't worry about large files).
 * The application should also provide a log of changes including some representation of the source record, the output record and the individual field changes (value from and value to) for each field.
 * Please implement as a command line program.
 * 
 * Example format:
 * 
 * {"leads":[
 * {
 * "_id": "jkj238238jdsnfsj23",
 * "email": "foo@bar.com",
 * "firstName":  "John",
 * "lastName": "Smith",
 * "address": "123 Street St",
 * "entryDate": "2014-05-07T17:30:20+00:00"
 * },
 * {
 * "_id": "jkj238238jdsnfsj23",
 * "email": "bill@bar.com",
 * "firstName":  "John",
 * "lastName": "Smith",
 * "address": "888 Mayberry St",
 * "entryDate": "2014-05-07T17:33:20+00:00"
 * }]
 * }
 * 
 * This program should be called as follows:
 * php [--strict --case] de-duplicate.php inputFileName [outputFileName logFileName]
 * If the strict flag is set, the program will require each entry to have the exact six fields shown in
 * the above examples and no others. This aligns with the program spec "identically structured json records".
 * However, for the program to work all that is necessary is the entryDate field for sorting and the 
 * _id and email files for comparisons. There is no reason why other record differences should make the program
 * fail. For instance, a record without an address or a record with an added telephone number. Therefore, the
 * default is for strict to be off.
 * If the case flag is set then the program will not consider email addresses to be case sensitive. In other words,
 * timfrobisher@gmail.com will be different from TimFrobisher@gmail.com. Again, the default for case is off.
 * 
 * @author Timothy Frobisher <timfrobisher@gmail.com>
*/

main();

/**
 * This function is the entry point and main logic for the program.
 * 
 * It parses the command line arguments
 * It opens the required files
 * It decodes, validates, and sorts the input
 * It determines which entries are duplicates that require removal
 * It writes the ouput with required duplicates removed
 * It writes to the log
 * 
 * @return void
 */
function main(): void {
    [$strictInputFormat, $caseSensitiveEmail, $inputFileName, $outputFileName, $logFileName] = parseCommandLine();
    $usage = "Usage: php [--strict --case] de-duplicate.php inputFileName [outputFileName logFileName]\n"
        . "--strict: Require each input item to contain all of the expected fields and no others\n"
        . "--case: Consider email addresses to be case-sensitive\n";
    if($inputFileName === "") {
        exit($usage);
    }
    $outputFile = fopen($outputFileName, "w") or exit("Unable to open output file " . $outputFileName . " for writing.\n\n" . $usage);
    $logFile = fopen($logFileName, "w") or exit("Unable to open log file " . $logFileName . " for writing.\n\n" . $usage);
    $inputFileContents = file_get_contents($inputFileName);
    if($inputFileContents === false) {
        exit("Unable to open input file " . $inputFileName . " for reading.\n\n" . $usage);
    }
    $inputArray = json_decode(($inputFileContents), true);
    $inputValidation = validateInput($inputArray, $strictInputFormat);
    if($inputValidation !== "") {
        exit("Input file validation failed: " . $inputValidation . "\n");
    }
    $inputEntries = $inputArray["leads"];
    sortInput($inputEntries);
    [$removals, $logs] = removeDuplicates($inputEntries, $caseSensitiveEmail);
    writeOutput($inputEntries, $removals, $outputFile);
    writeLogs(count($removals), $inputFileName, $logs, $logFile);
}

/**
 * This function parses the command line arguments
 * 
 * @return array An array of flags and file arguments
 */
function parseCommandLine(): array {
    global $argv;
    
    $inputFileName = "";
    $outputFileName = "output.json";
    $logFileName = "de-duplicate.log";
    $rest_index = -1;
    $options = getopt("", ["strict", "case"], $rest_index);
    $strictInputFormat = isset($options["strict"]);
    $caseSensitiveEmail = isset($options["case"]);
    if($rest_index > 0) {
        if(isset($argv[$rest_index])) {
            $inputFileName  = $argv[$rest_index];
        }
        if(isset($argv[($rest_index + 1)])) {
            $outputFileName = $argv[($rest_index + 1)];
        }
        if(isset($argv[($rest_index + 2)])) {
            $logFileName = $argv[($rest_index + 2)];
        }
    }
    return [$strictInputFormat, $caseSensitiveEmail, $inputFileName, $outputFileName, $logFileName];
}

/**
 * This function determines which entries need to be removed as duplicates
 * 
 * This function relies on the fact that the entries are sorted in descending order by date and position in the input
 * The function simply loops through the entries, keeping track of which ids and emails have been seen.
 * If a duplicate is seen, it is marked for removal in the removals array and logged in the logs array
 * 
 * @param array &$entries The input array
 * @param bool $caseSensitiveEmail If true, emails will be considered case sensitive
 * @return array 2 element array containing an array of removal indices and an array of log comments
 */
function removeDuplicates(array &$entries, bool $caseSensitiveEmail): array {
    $seen_emails = array();
    $seen_ids = array();
    $removals = array();
    $logs = array();
    foreach($entries as $index => $elements) {
        $email = $caseSensitiveEmail ? $elements["email"] : strtolower($elements["email"]);
        $id = $elements["_id"];
        if(isset($seen_emails[$email])) {
            $removals[$index] = true;
            $logs[] = makeLogEntry($entries[$index], $entries[$seen_emails[$email]], "email", $email);
        }
        elseif(isset($seen_ids[$id])) {
            $removals[$index] = true;
            $logs[] = makeLogEntry($entries[$index], $entries[$seen_ids[$id]], "_id", $elements["_id"]);
        }
        else {
            $seen_emails[$email] = $index;
            $seen_ids[$id] = $index;
        }
    }
    return [$removals, $logs];
}

/**
 * This function creates the required log entries
 * 
 * @param array $removed The duplicate entry that is being removed
 * @param array $retained The duplicate entry that is being retained
 * @param string $key The duplicate key, either email or _id
 * @param string $value The value of the key
 * @return string The log entry indicating the duplicate key, the removed and retained entries, and their differences
 */

function makeLogEntry(array $removed, array $retained, string $key, string $value): string {
    $diff = "Values Changed: ";
    foreach($removed as $index => $val) {
        if(isset($retained[$index])) {
            if($retained[$index] === $removed[$index]) {
                continue;
            }
            $ret = ", Retained: " . $retained[$index];
        }
        else {
            $ret = "";
        }
        $diff .= $index . ") Removed: " . $removed[$index] . $ret . ". ";
    }
    return "Found duplicate " . $key . " with value: " . $value . "\n"
        . "Removed: " . json_encode($removed) . "\n"
        . "Retained: " . json_encode($retained) . "\n"
        . $diff . "\n";
}

/**
 * This function writes the output to the designated file in the "same format" as the input.
 * 
 * This function writes the output to the designated file in the "same format" as the input.
 * The "same format" requirement is why I am "manually" outputting the JSON instead of using
 * json_decode with JSON_PRETTY_PRINT. A call to json_decode with JSON_PRETTY_PRINT will produce
 * "properly" indented lines, which seems desirable except that the example input file did not
 * have any indents. Also, for some reason there were two spaces in fron othe first names in
 * the example input file, which would be lost using json_decode.
 * Also, sorting the index using uasort() in order to maintain the original indices was an
 * intentional choice. This allows me to write the output in the same order as the original input,
 * just with duplicates removed. Although this was not a specified requirement, it seemed like
 * a reasonable thing to do. This is safe because the original array was indexed contiguously from
 * 0 to n-1 and no array elements were deleted.
 * 
 * @param array $contents The input array as sorted by sortInput()
 * @param array $removals An array of indices that have been "removed" and therefore should be skipped
 * @param resource $outputFile The resource for the open log file
 * @return void
 */

function writeOutput(array $contents, array $removals, $outputFile): void {
    $entries = array();
    for($i = 0; $i < count($contents); ++$i) {
        if(isset($removals[$i])) {
            continue;
        }
        $fields = array();
        foreach($contents[$i] as $key => $value) {
            $whiteSpace = $key !== "firstName" ? " " : "  "; //In the example file, there were two spaces in front of the first names
            $fields[] = "\"" . $key . "\":" . $whiteSpace . "\"" . str_replace("\"", "\\\"", $value) . "\"";
        }
        $entries[] = "{\n" . implode(",\n", $fields) . "\n}";
    }
    $output = "{\"leads\":[\n" . implode(",\n", $entries) . "]\n}";
    fwrite($outputFile, $output);
    fclose($outputFile);
}

/**
 * Writes to and closes the log file.
 * 
 * @param int $count The number of entries removed
 * @param string $inputFile The filename of the input file
 * @param array $logs An array of log comments
 * @param resource $logFile The resource for the open log file
 * @return void 
 */
function writeLogs(int $count, string $inputFile, array $logs, $logFile): void {
    $start = "At " . date(DateTimeInterface::ATOM) . " processed " . $inputFile . " and removed " . $count 
        . " duplicate entr" . ($count === 1 ? "y" : "ies") . ".\n\n";
    fwrite($logFile, $start . implode("\n", $logs));
    fclose($logFile);
}

/**
 * Sorts the input in descending order according to entryDate. If times are equal, then descending order according to order in the input file.
 * 
 * @param array &$inputEntries The array of entries to be sorted
 * @return void
 */
function sortInput(array &$inputEntries): void {
    for($i = 0; $i < count($inputEntries); ++$i) {
        $inputEntries[$i]["index"] = $i;
    }
    uasort($inputEntries, "Frobisher\compareEntries");
    for($i = 0; $i < count($inputEntries); ++$i) {
        unset($inputEntries[$i]["index"]);
    }
}

/**
 * Comparison function to pass to uasort()
 * 
 * @param $a First entry being compared
 * @param $b Second entry being compared
 * @return int -1, 0, 1 depending on comparison
 */
function compareEntries(array $a, array $b): int {
    if($b["entryDate"] === $a["entryDate"]) {
        return $b["index"] <=> $a["index"];
    }
    return $b["entryDate"] <=> $a["entryDate"];
}

/**
 * This function validates the input. 
 * 
 * The function checks that the top level has only one element, called leads.
 * It validates three items in each leads entry.
 * 1. It checks that the _id exists and is not blank.
 * 2. It checks that the email exists exists and is not blank
 * 3. It checks that the entryDate exists and is not blank
 * 4. Additionally, since the entryDate is going to be used in the de-duping logic, it validates the date format.
 * Since the purpose of the program is only to remove duplicates, it does not validate the content of the _id or email fields.
 * For the same reason, it does not check if the other three expected fields exist or if any additional fields exist, unless the
 * strict flag is set.
 * 
 * @param array $inputArray The entire contents of the input file
 * @param bool $strictInputFormat If true, the function requires each entry to have all of the expected keys and no others
 * @return string An error message if there is an error or an empty string if not
 */
function validateInput(array $inputArray, bool $strictInputFormat): string {
    if(count($inputArray) !== 1) {
        return " The first level of the json object should have only a single element, called \"leads\" but instead has " . count($inputArray) . " elements";
    }
    $format = DateTimeInterface::ATOM;
    foreach($inputArray as $leads => $leadArray) {
        if($leads !== "leads") {
            return "The first level of the json object should be called \"leads\" but instead is called \"" . $leads . "\"";
        }
        foreach($leadArray as $index => $entry) {
            $e = validateInputField($entry, "_id", $index + 1); 
            if($e !== "") {
                return $e;
            }
            $e = validateInputField($entry, "email", $index + 1); 
            if($e !== "") {
                return $e;
            }
            $e = validateInputField($entry, "entryDate", $index + 1); 
            if($e !== "") {
                return $e;
            }
            $date = DateTime::createFromFormat($format, $entry["entryDate"]);
            if(!$date || $date->format($format) !== $entry["entryDate"]) {
                return "The \"entryDate\" field of entry " . ($index + 1) . " is not valid";
            }
            if($strictInputFormat) {
                if(count($entry) > 6) {
                    return "Entry " . ($index + 1) . " has too many fields.";
                }
                $e = validateInputField($entry, "firstName", $index + 1); 
                if($e !== "") {
                    return $e;
                }
                $e = validateInputField($entry, "lastName", $index + 1); 
                if($e !== "") {
                    return $e;
                }
                $e = validateInputField($entry, "address", $index + 1); 
                if($e !== "") {
                    return $e;
                }
            }
        }
    }
    return "";
}

/**
 * Validates a field in an entry by ensuring the field exists and is not empty
 * 
 * @param array &$entry The entry being validated
 * @param string $key The key of field being validated in the entry
 * @param int $num The index of the entry in the input file
 * @return string
 */
function validateInputField(array &$entry, string $key, int $num): string {
    if(!isset($entry[$key])) {
        return "Entry " . $num . " of the input does not have an " . $key . " field";
    }
    if(trim($entry[$key]) === "") {
        return "The " . $key . " field of entry " . $num . " is empty";
    }
    return "";
}