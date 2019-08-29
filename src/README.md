# _Comments and Reviews_ Retrieval Module

The module accepts URL from the user and downloads comments and reviews data from the URL.<br/>
It parses the response data, retrieves the comments and reviews, then, creates and stores the information in CSV format.

If the data retrieval takes longer than approximately two (2) minutes, the module notifies the user and switches to background process. 
 
 Output file delivery options include:
 1. Instant download. This is the default option.
 2. File delivery to email address. The module automatically uses this option once data retrieval is done as background process.<br/>The user can as well select this option as his preferred delivery method irrespective of process level.