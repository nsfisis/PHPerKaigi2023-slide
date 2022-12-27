https://www.php.net/manual/ja/language.references.whatdo.php
https://www.phpinternalsbook.com/



```c
ZEND_VM_HANDLER(30, ZEND_ASSIGN_REF, VAR|CV, VAR|CV, SRC)
{
	USE_OPLINE
	zval *variable_ptr;
	zval *value_ptr;

	SAVE_OPLINE();
	value_ptr = GET_OP2_ZVAL_PTR_PTR(BP_VAR_W);
	variable_ptr = GET_OP1_ZVAL_PTR_PTR_UNDEF(BP_VAR_W);

	if (OP1_TYPE == IS_VAR &&
	           UNEXPECTED(Z_TYPE_P(EX_VAR(opline->op1.var)) != IS_INDIRECT)) {

		zend_throw_error(NULL, "Cannot assign by reference to an array dimension of an object");
		variable_ptr = &EG(uninitialized_zval);
	} else if (OP2_TYPE == IS_VAR &&
	           opline->extended_value == ZEND_RETURNS_FUNCTION &&
			   UNEXPECTED(!Z_ISREF_P(value_ptr))) {

		variable_ptr = zend_wrong_assign_to_variable_reference(
			variable_ptr, value_ptr OPLINE_CC EXECUTE_DATA_CC);
	} else {
	    // !!!
		zend_assign_to_variable_reference(variable_ptr, value_ptr);
	}

	if (UNEXPECTED(RETURN_VALUE_USED(opline))) {
		ZVAL_COPY(EX_VAR(opline->result.var), variable_ptr);
	}

	FREE_OP2();
	FREE_OP1();
	ZEND_VM_NEXT_OPCODE_CHECK_EXCEPTION();
}

// https://github.com/php/php-src/blob/php-8.2.3/Zend/zend_execute.c#L533-L557
static inline void zend_assign_to_variable_reference(zval *variable_ptr, zval *value_ptr)
{
	zend_reference *ref;

	if (EXPECTED(!Z_ISREF_P(value_ptr))) {
		ZVAL_NEW_REF(value_ptr, value_ptr);
	} else if (UNEXPECTED(variable_ptr == value_ptr)) {
		return;
	}

	ref = Z_REF_P(value_ptr);
	GC_ADDREF(ref);
	if (Z_REFCOUNTED_P(variable_ptr)) {
		zend_refcounted *garbage = Z_COUNTED_P(variable_ptr);

		if (GC_DELREF(garbage) == 0) {
			ZVAL_REF(variable_ptr, ref);
			rc_dtor_func(garbage);
			return;
		} else {
			gc_check_possible_root(garbage);
		}
	}
	ZVAL_REF(variable_ptr, ref);
}

// https://github.com/php/php-src/blob/php-8.2.3/Zend/zend_types.h#L1077-L1086
#define ZVAL_NEW_REF(z, r) do {									\
		zend_reference *_ref =									\
		(zend_reference *) emalloc(sizeof(zend_reference));		\
		GC_SET_REFCOUNT(_ref, 1);								\
		GC_TYPE_INFO(_ref) = GC_REFERENCE;						\
		ZVAL_COPY_VALUE(&_ref->val, r);							\
		_ref->sources.ptr = NULL;									\
		Z_REF_P(z) = _ref;										\
		Z_TYPE_INFO_P(z) = IS_REFERENCE_EX;						\
	} while (0)

  zend_reference *_ref = (zend_reference *)malloc(/* 略 */);
  _ref->refcount = 1;
  ZVAL_COPY_VALUE(&_ref->val, value_ptr);
  value_ptr->value.ref = _ref;
  value_ptr->type_info = IS_REFERENCE;

#define GC_SET_REFCOUNT(p, rc)		zend_gc_set_refcount(&(p)->gc, rc)
static zend_always_inline uint32_t zend_gc_set_refcount(zend_refcounted_h *p, uint32_t rc) {
	p->refcount = rc;
	return p->refcount;
}
#define GC_TYPE_INFO(p)				(p)->gc.u.type_info

	do {												\
		zval *_z1 = (z);								\
		const zval *_z2 = (v);							\
		zend_refcounted *_gc = Z_COUNTED_P(_z2);		\
		uint32_t _t = Z_TYPE_INFO_P(_z2);				\
		ZVAL_COPY_VALUE_EX(_z1, _z2, _gc, _t);			\
	} while (0)

#define Z_TYPE_INFO(zval)			(zval).u1.type_info
#define Z_TYPE_INFO_P(zval_p)		Z_TYPE_INFO(*(zval_p))

#define Z_REF_P(zval_p)				Z_REF(*(zval_p))
#define Z_REF(zval)					(zval).value.ref

#define ZVAL_REF(z, r) do {										\
		zval *__z = (z);										\
		Z_REF_P(__z) = (r);										\
		Z_TYPE_INFO_P(__z) = IS_REFERENCE_EX;					\
	} while (0)

#define GC_ADDREF(p)				zend_gc_addref(&(p)->gc)

static zend_always_inline uint32_t zend_gc_addref(zend_refcounted_h *p) {
	ZEND_RC_MOD_CHECK(p);
	return ++(p->refcount);
}
```







# php-src

```c
typedef struct _zval_struct     zval;

// https://github.com/php/php-src/blob/php-8.2.3/Zend/zend_types.h#L315-L340
struct _zval_struct {
	zend_value        value;			/* value */
	union {
		uint32_t type_info;
		struct {
			ZEND_ENDIAN_LOHI_3(
				zend_uchar    type,			/* active type */
				zend_uchar    type_flags,
				union {
					uint16_t  extra;        /* not further specified */
				} u)
		} v;
	} u1;
	union {
		uint32_t     next;                 /* hash collision chain */
		uint32_t     cache_slot;           /* cache slot (for RECV_INIT) */
		uint32_t     opline_num;           /* opline number (for FAST_CALL) */
		uint32_t     lineno;               /* line number (for ast nodes) */
		uint32_t     num_args;             /* arguments number for EX(This) */
		uint32_t     fe_pos;               /* foreach position */
		uint32_t     fe_iter_idx;          /* foreach iterator index */
		uint32_t     property_guard;       /* single property guard */
		uint32_t     constant_flags;       /* constant flags */
		uint32_t     extra;                /* not further specified */
	} u2;
};

// https://github.com/php/php-src/blob/php-8.2.3/Zend/zend_types.h#L295-L313
typedef union _zend_value {
	zend_long         lval;				/* long value */
	double            dval;				/* double value */
	zend_refcounted  *counted;
	zend_string      *str;
	zend_array       *arr;
	zend_object      *obj;
	zend_resource    *res;
	zend_reference   *ref;
	zend_ast_ref     *ast;
	zval             *zv;
	void             *ptr;
	zend_class_entry *ce;
	zend_function    *func;
	struct {
		uint32_t w1;
		uint32_t w2;
	} ww;
} zend_value;

// https://github.com/php/php-src/blob/php-8.2.3/Zend/zend_types.h#L548-L560
/* Regular data types: Must be in sync with zend_variables.c. */
#define IS_UNDEF					0
#define IS_NULL						1
#define IS_FALSE					2
#define IS_TRUE						3
#define IS_LONG						4
#define IS_DOUBLE					5
#define IS_STRING					6
#define IS_ARRAY					7
#define IS_OBJECT					8
#define IS_RESOURCE					9
#define IS_REFERENCE				10
#define IS_CONSTANT_AST				11 /* Constant expressions */

// https://github.com/php/php-src/blob/php-8.2.3/Zend/zend_types.h#L537-L541
struct _zend_reference {
	zend_refcounted_h              gc;
	zval                           val;
	zend_property_info_source_list sources;
};

typedef struct _zend_refcounted_h {
	uint32_t         refcount;			/* reference counter 32-bit */
	union {
		uint32_t type_info;
	} u;
} zend_refcounted_h;
```




# PHP Manual


https://www.php.net/manual/en/language.references.whatdo.php


```php
<?php

$a =& $b;
```

> $a and $b are completely equal here. $a is not pointing to $b or vice versa. $a and $b are pointing to the same place.

> If you assign, pass, or return an undefined variable by reference, it will get created.

```php
<?php

function foo(&$var) {}

foo($a);
$b = [];
foo($b['b']);
// array_key_exists('b', $b) // => true

$c = new stdClass();
foo($c->d);
// property_exists($c, 'd') // => true
```

```php
<?php

function& foo() {}

$a =& foo();

$b =& new stdClass();
// => Error!
```

> If you assign a reference to a variable declared global inside a function,
> the reference will be visible only inside the function. You can avoid this by
> using the `$GLOBALS` array.



!!!

```php
<?php

$var1 = "Example variable";
$var2 = "";

function global_references($use_globals) {
    global $var1, $var2;
    if ($use_globals) {
        $GLOBALS["var2"] =& $var1; // visible also in global context
    } else {
        $var2 =& $var1; // visible only inside the function
    }
}

global_references(false);
echo "var2 is set to '$var2'\n"; // var2 is set to ''
global_references(true);
echo "var2 is set to '$var2'\n"; // var2 is set to 'Example variable'
```

> Think about `global $var;` as a shortcut to `$var =& $GLOBALS['var'];`. Thus
> assigning another reference to `$var` only changes the local variable's
> reference.




> If you assign a value to a variable with references in a foreach statement,
> the references are modified too.

```php
<?php

$ref = 0;
$row =& $ref;

foreach ([1, 2, 3] as $row) {
  // do something
}
echo $ref; // 3: last element of the iterated array
```


!!!

> While not being strictly an assignment by reference, expressions created with
> the language construct `array()` can also behave as such by prefixing `&` to the
> array element to add. Example:

```php
<?php

$a = 1;
$b = [2, 3];
$arr = [&$a, &$b[0], &$b[1]];
$arr[0]++; $arr[1]++; $arr[2]++;
var_dump($a);
// => 2
var_dump($b);
// => [3, 4]
```


!!!

> Note, however, that references inside arrays are potentially dangerous. Doing
> a normal (not by reference) assignment with a reference on the right side
> does not turn the left side into a reference, but references inside arrays
> are preserved in these normal assignments. This also applies to function
> calls where the array is passed by value. Example:

```php
<?php

/* Assignment of scalar variables */
$a = 1;
$b =& $a;
$c = $b;
$c = 7; //$c is not a reference; no change to $a or $b

/* Assignment of array variables */
$arr = [1, 2];
$a =& $arr[0]; //$a and $arr[0] are in the same reference set
$arr2 = $arr; //not an assignment-by-reference!
$arr2[0]++;
$arr2[1]++;
/* $a == 2, $arr == [2, 2] */
/* The contents of $arr are changed even though it's not a reference! */
```

> In other words, the reference behavior of arrays is defined in an
> element-by-element basis; the reference behavior of individual elements is
> dissociated from the reference status of the array container.



!!!

https://www.php.net/manual/en/language.references.arent.php

```php
<?php

function foo(&$var) {
  $var =& $GLOBALS["baz"];
}
foo($bar); 
```


https://www.php.net/manual/en/language.references.pass.php


リファレンス渡しできるもの

* 変数
* リファレンス返しする関数の返り値

```php
<?php

$a = 1;
$b =& $a;
unset($a); 
```

https://www.php.net/manual/en/language.references.spot.php

Reference っぽくない reference

```php
<?php

// global $var;
$var =& $GLOBALS["var"];
```




# PHP Internals Book

https://www.phpinternalsbook.com/

## https://www.phpinternalsbook.com/php7/zvals/basic_structure.html

> The IS_REFERENCE type in conjunction with the zend_reference *ref member is
> used to represent a PHP reference. While from a userland perspective
> references are not a separate type, internally references are represented as
> a wrapper around another zval, that can be shared by multiple places.


## https://www.phpinternalsbook.com/php7/zvals/references.html

> People will commonly say that “$b is a reference to $a”. However, this is not
> quite correct, in that references in PHP have no concept of directionality.
> After $b =& $a, both $a and $b reference a common value, and neither of the
> variables is privileged in any way.

-----

> Normally, PHP does not track who or what makes use of a given reference. The
> only knowledge that is stored is how many users there are (through the
> refcount), so that the reference may be destroyed in time.

> However, due to the introduction of typed properties in PHP 7.4, we do need
> to track of which typed properties make use of a certain reference, in order
> to enforce property types for indirect modifications through references:

```php
<?php

class Test {
  public int $prop = 42;
}
$test = new Test();
$ref =& $test->prop;
$ref = "string"; // TypeError
```

> The sources member of zend_reference stores a list of zend_property_info
> pointers to track typed properties that use the reference. Macros like
> ZEND_REF_HAS_TYPE_SOURCES(), ZEND_REF_ADD_TYPE_SOURCE(), and
> ZEND_REF_DEL_TYPE_SOURCE()




# プロポーザル

https://fortee.jp/phperkaigi-2023/proposal/95e4dd94-5fc7-40fe-9e1a-230e36404cbe

## 詳説「参照」：PHP 処理系の実装から参照を理解する

> PHP における参照に似た機能は、他の言語にも存在しています。C のポインタ、C++ の参照、Java の参照型、C# の参照渡し……。しかしこれらは、それぞれ細かな点で PHP のそれとは異なっています。
> PHP における参照を完全に理解すべく、1) PHP レベルでの挙動を観察し、2) PHP 処理系 (https://github.com/php/php-src) のソースコードを追いかけます。
> 
> 対象: 重箱の隅をつつきたい PHPer、または PHP の language lawyer になりたい人。PHP 処理系は C で書かれていますが、C の知識は (あまり) 要求しないようにするつもりです
> 目標: PHP の参照を、実装レベルで完全に理解すること、また、php-src を少しだけ探索できるようになること
> 話さないこと: 参照のメリット・デメリットや使うべき場面
