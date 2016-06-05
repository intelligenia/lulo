# API

The base classes are fully documented by let's make a summary of the API.

## Loading data from the database

The basic methods are **dbLoad** and **dbLoadAll**.

This methods support only a limited set of conditions and queries are the prefered
way of loading data from the database.

## dbLoad

**dbLoad** loads one object or returns null if it doesn't exist.

## dbLoadAll

**dbLoadAll** returns a query, queryresult or collection of objects that comply
with the condition.

### Conditions

Conditions is an array of pairs <attribute>=>[<operator> => <value>].

More information about the format can be found [here](db/db.class.php).

### Order
An array where keys are attributes and values are "ASC" or "DESC".

### Limit
Limit can be a number (size of returned elemetns) and an array of the form [offset, size].

## Writing data to the database

You should only use **dbSave**. This methos checks if the model object already exists
in the database and, if that's the case, updates it instead of inserting a new tuple.