# Queries

Conditions in dbLoad or dbLoadAll (see [model_api documentation](docs/model_api)) are a bit limited.

That's the reason we have developed a new way of doing queries inspired by the QuerySet API of Django.

## Making a query

To make a query call **objects** static method of your model. This call will
return q Query that can be chained by other methods:

- **filter**: filter the results. Accepts an array of array with conditions. That is
the condition is in disjunctive normal form. E. g.:
  - filter(["A"=>1, "B"=>2], ["B"=>3, "C"=>4]) is equal to (A=1 AND B=2) OR (B=3 AND C=4)
  - filter(["A"=>1], ["B"=>3, "C"=>4])->filter(["X"=>2, "Y"=>3], ["W"=>5]) is equal to ( (A=1) OR (B=3 AND C=4) ) AND ( (X=2 AND Y=3) OR W=5 )
- **exclude**: same as filter but excludes the condition of the selected data.
- **order**: accepts an array of attributes with values "ASC" or "DESC".
- *limit*: accepts an array of type [offset, size] or the size of the results.
- **get**: returns the first element.
- **count**: return the number of elements that comply with the filter.
- **distinct**: apply distinct to the query to avoid getting repeated values.

Examples:
```php

// Search for all users whose name is "James" and surname starts with "Smith"
// Order the results by id (ascending)
$users = User::objects()->filter([["last_name__startswith"=>"Smit"],["first_name"=>"James"]])
			->order(["id" => "asc"]);


// Return the first tag which description starts with "Engineering".
try{
    Tag::objects()->filter(["description__startswith" => "Engineering"])->get();
}catch(\Exception $e){
    print "- There is no Tag object.'<br/>";
}
print "- There is Tag object.'<br/>";

````

## Dealing with results

Query allow you to transverse through the results without having to worry about
where is the data. Query implements ArrayAccess, Iterator and Countable interfaces.

```php
// Get tags using a Query
$tagCondition = ["name__contains"=>"music"];
$loadedTags = Tag::objects()->filter($tagCondition);
// Looping through results
foreach($loadedTags as $loadedTag){
    $user->dbAddRelation("tags", $loadedTag);
    print " -".$loadedTag->str()."<br/>";
}
````

## Filter operators
By default, filter operator is "equal to" but sometimes we need more advanced operators
or [field lookups](https://docs.djangoproject.com/es/1.9/ref/models/querysets/#field-lookups) as Django team call them.

The way to call a specific operator is **attribute__operator => value**.

For example:
```php
$last_name = "Jones";
$countJones = User::objects()->filter(["last_name__contains" => $last_name])->count();
```

### Filter operator reference

- **contains**: search if term is contained in the value of the field. Implemented with LIKE.
- **notcontains**: search if term is not contained in the value of the field. Implemented with NOT LIKE.
- **startswith**: search if term is at the begining of the value of the field. Implemented with LIKE.
- **endswith**: search if term is at the end of the value of the field. Implemented with LIKE.
- **in**: search if field is one of the elements in a list. Implemented with IN.
- **range**: search if field is in one interval. Implemented with BETWEEN.
- **eq**: search if field is equal to a value. Implemented with =.
- **noteq**: search if field is different to a value. Implemented with <>.
- **lt**: search if field is less than a value. Implemented with <.
- **lte**: search if field is less or equal than a value. Implemented with <=.
- **gt**: search if field is greater than a value. Implemented with >.
- **gte**: search if field is greater or equal than a value. Implemented with >=.

### Filtering by remote conditions

To filter by a remote condition, use the following syntax: **relationship_name::remot_attribute => value**.

Note that relationships have also an inverse relationship so the relationship_name is the name of the relationship
according to what model is executing the query.

For example:
```php
// Count children of parent Engineering tags
$countEngTagsChildren = Tag::objects()->filter(["parent::name__endswith" => "Engineering"])->count();

// Tags related to users which the "Jones" surname
$jonesUserTags = Tag::objects()->filter(["users::last_name" => "Jones"]);
```