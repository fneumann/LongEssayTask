# LongEssayTask (Pre-Test Version)
Plugin for the LMS ILIAS open source to realize exams with writing of long texts.

This pre-test version supports an initial set of functionality from the [EDUTIEK project](https://www.edutiek.de)
to provide a proof of concept and architectory. The main functionality is demonstrated in a [Workshop at the 21st ILIAS Conference in September 2022](https://youtu.be/Cn0gJOyj3bM)

It was successfully tested at:
* Universität Bochum (44 participants)
* Justiz Bremen (26 participants)
* Universität Bielefeld (29 participants)
* Universität Münster (69 participants)

The  development of the pre-test version is finished. This repository will only get bug fixes.

The **Pilot Version** is under development. it is renamed to **LongEssayAssessment** and maintained in a [new GitHub Repository](https://github.com/EDUTIEK/LongEssayAssessment). 



## Installation

1. Copy the plugin to Customizing/global/plugins/Services/Repository/RepositoryObject
2. Go to the plugin folder
3. Execute ````composer install --no-dev````
4. Install the plugin in the ILIAS plugin administration

## Update from Git

1. Go to the plugin folder
2. Execute the following commands:
````
 rm -R vendor
 rm composer.lock
 composer install --no-dev
````
