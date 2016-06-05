# Requirements

## Inheritance

If you want to have a writable model, inherit from LuloModel. Otherwise, make your
model inherit from ROModel.

## Class autoloading

Lulo needs you have defined a class automatic loading function. Inverse relationships
are automatically created and classes will be automatically loadede as well.

I recommend you to use your project directories as namespaces and create a basic
autoloading mechanism that, this way, can know where each class is.

For example, in the example we deliver in this project, class autoloading is very simple:

```php
spl_autoload_register("load_test_models");

function load_test_models($class_name){
    $class_file_name = strtolower($class_name);
    if(strpos($class_name, "\\") === false){
        $root_path = LULO_DIR."/tests/models";
        $class_path = "{$root_path}/{$class_file_name}.class.php";
    }else{
        $root_path = PARENT_LULO_DIR;
	$class_file_name = str_replace("\\", "/",  strtolower($class_name));
	$class_path = "{$root_path}/{$class_file_name}.class.php";
    }	
    if(file_exists($class_path)){
        require_once $class_path;
        return true;
    }
    return false;
}
```

## DB

A big and important requirement is having a DB class with the right DB_ENGINE and
DRIVER.

We usually use MySQL but if you want to use another DBMS, created a new DB class
inherit from DB and overwrite the fields DB_ENGINE and DRIVER, and of course
the methods that rely on specific dialects of SQL.

We have tried to make DB as DBMS-agnostic as we could but if you have some issue
don't hesitate to contact us. We really appreciate any help in this project.

# Defining models
## Basic data

- **DB**: class constant that contains the name of the database abstraction layer class.
- **TABLE_NAME**: class constant that contains the name of the table that will read and write this model.
- **CLASS_NAME**: this class name. It is also a class constant.
- **META**: static array that contains several metainformation about the class. Useful for future admin interfaces:
  - **model_description**: string with a model description.
  - **verbose_name**: readable model name.
  - **verbose_name_plural**: readable model name in plural.
  - **gender**: gender of the model ("f" for female and "m" for male).
  - **order**: order in management interfaces. It is an array with the form ["attribute"=>"ASC|DESC"]
- **PK_ATTRIBUTES**: static array with a list of the attributes that form the primary key.


## Attributes

### Definition

To define the attributes we must overwrite $ATTRIBUTES static array. This array
is formed by pairs key-value where the key is the attribute name and the value
is an array with its properties.

Attribute properties:

- **type**: type of the attribute. Can have the values: "string", "int", "float" and "blob".
- **subtype**: if type is not enough, subtype allows you to define additional restrictions. For example, "date" or "datetime" fot type "string".
- **max_length**: if is a string, the max_length of string it have. If it doesn't  have a max_length, Lulo will assume the string is infinite (LONGTEXT).
- **default**: default value.
- **null**: inform if the attribute is nullable.
- **verbose_name**: human readable name with an explanation of what is this attribute.
- **help_text**: optional extended explanation for this attribute.
- **auto**: if true, it assumes its value is automatically computed.

### Example

![Attributes for an example model](/docs/images/examples/lulo-attributes.png)

### Use

There is a magic method in the models that allows you to directely access to the
attributes as it were real object attributes. That is:

```php
// Suppose we have a model Photo with these attributes:
protected static $ATTRIBUTES = [
    // Primary key
    "stack"=>["type"=>"string", "max_length"=>32, "default"=>TEST_STACK, "verbose_name"=>"Web site of this post", "auto"=>true],
    "id" => ["type"=>"int", "verbose_name"=>"Unique identifier", "auto"=>true],
    // Data
    "title" => ["type"=>"string", "max_length"=>64, "verbose_name"=>"Title"],
    "title_slug" => ["type"=>"string", "max_length"=>32, "verbose_name"=>"Slug", "auto"=>true],
    "content" => ["type"=>"string", "subtype"=>"doku", "verbose_name"=>"Post content"],
    "owner_id" => ["type"=>"int", "relationship"=>"owner", "verbose_name"=>"User owner of this post"],
    // Datetime fields
    "last_update_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Last time this object was updated", "auto"=>true],
    "creation_datetime" => ["type"=>"string", "subtype"=>"datetime", "verbose_name"=>"Creation datetime", "auto"=>true],
];

// We load a photo and would want to know its title
$photo = Photo::dbLoad(["title_slug"=>"my-photo-on-2016-vacation"]);

// Access directly as we would do with a real object attribute
print $photo->title;

// If we want to edit one attribute, we follow the same principle: treat attributes
// as if they were real object attributes
$photo->title = "First photo of my 2016 vacation in Ibiza";
```

## Relationships

### Definition

- RELATED_MODELS: static array that contains the name of the classes this model is related. Even if it is by an inverse relationship.
- RELATIONSHIPS: define a static array that contains the relationships. The key of the array is the relationship name and the value
is the properties of that relationship.
  - **type**: type of the relationship: **ManyToMany**, **ForeignKey** or **OneToOne**.
  - **model**: remote model this model has a relationship.
  - **verbose_name**: readable name of the relationship.
  - **related_name**: name of the inverse relationship. This name will be used to create a dynamic attribute in the remote model objects to access to current model objects.
  - **related_verbose_name**: readable name of the inverse relationship.
  - **readonly**: is the relationship read only?.
  - For **ManyToMany** relationships:
    - **junctions**: list of intermediate tables. If there is more than one table, this relationship will be readonly.
    - **conditions**: array of arrays that contains the link between this model's table and the first nexus, the link between the first and the second nexus and so on.
  - For **ForeignKey** and **OneToOne** relationships:
    - **nullable**: if the relationship is nullable.
    - **on_master_deletion**: could take "delete" and ["set" => ["attribute1"=>null, "attribute2"=>null, ..., "attributeN"=>null]]. The former deletes the servant when the master is deleted, the latter sets some attributes of the servant as null.

### Definition of foreign keys in model attributes

It is possible to define foreign key relationships based on attributes:

We only have to use the subtype "ForeignKey and include the following extra metaattributes:
- **name**: name of the relationship.
- **on**: model and model attribute that will link with the current attribute of the form **Model.attribute**.

The rest of the attributes are the same and can have the same values as have
a standard relationship.

Maybe this example could make it clearer:

```php
protected static $ATTRIBUTES = [
    // ...
    // Foreign key
    "user_id" => [
        "type"=>"int", "subtype"=>"ForeignKey", "name"=>"user", "on"=>"lulo\\tests\models\User.id", "related_name"=>"photos",
        "verbose_name"=>"Photo owner",
        "related_verbose_name" => "User photos",
        "nullable" => false,  "readonly"=>false,
        "on_master_deletion" => "delete"
    ],
    // Other attributes ...
   ];
```

### Example

![Relationships for an example model](/docs/images/examples/lulo-relationships.png)


## init

Don't forget to call init at the end of your script. This is needed to create the inverse relationships

```php
/*
 * Mandatory initialization at the end of the class file.
 * */
Photo::init();
```
