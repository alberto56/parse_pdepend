This script is meant to be used via the command line to parse
output from pDepend into easy-to-read information about logical
lines of code in files and functions.

Usage:

Install [pDepend](http://pdepend.org/)

Run pDepend to generate an xml file, for example with Drupal

you may run something like:

    pdepend --summary-xml='sites/default/files/ci/test.xml'\
    --suffix=test,install,module\
    sites/all/modules/custom

Run:

    parse_pdepend.php sites/default/files/ci/test.xml dest.csv
    in this version dest.csv is ignored. Eventually dest.csv might
    be used to export the data for use with Jenkins.

by Albert Albala, https://drupal.org/user/245583
