This repository contains the solution for the "Drupal Backend Developer" test at Eksponent.

The solution contains the following:

- A event node type, containing event information such as title, description, start/end date, tickets, ticket prices and event image.
- A view for showing events sorted by event date. The view shows a teaser of the event. The view and event teaser styling is very roughly templated/styled for now.
- A custom event module, containing all event logic:
  - A field formatter for the ticket field (deciding what to show depending on how many tickets are left)
  - An event importer for importing external events from a public JSON API endpoint. The importer make sure to either create new event nodes or update existing nodes based on a check on UUID
  - As part of the event importer, event images are importet and added as new Media Image entities.
  - A new drush command which can be used for starting the import manually or could be used as part of a system cronjob.
- The JSON:API and JSON:API Extras module has been enabled and configuredt to:
  - Expose event data from a public endpoint: https://eksponent-test.ddev.site/jsonapi/events
  - Configured to only show relevant data such as title, description, start/end date, tickets, ticket prices and event image.
  - Disabled all other JSON:API resources, as we only want to expose the event data.

Future work:
Some things could be done to improve the current solution:

- Add better templating/styling for both the event node template and the event view template.
- The logic for the event importer could be updated to also include:
  - Add logic to delete or unpublish old events which has expired.
  - Add logic to only import events which is happening in the future, omitting already expired events.
  - Add logic to delete or unpublish events which has been cancelled.
