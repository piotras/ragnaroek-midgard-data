// Blog template
'blog' => array
(
    // Basic metadata for the site template
    'name' => 'blog',
    'title' => 'Weblog', 
    'description' => 'Typical weblog template with comments enabled',
    'credits' => 'Henri Bergius, the Midgard Project',
    'license' => 'LGPL',
    'examples' => array
    (
        'http://www.creatingleet.com/' => 'Leet.tv team blog',
	'http://www.protoblogr.net/'   => 'Protoblogr: Jerry Jalava\'s blog',
    ),


    // Groups that should be created / used for this site template      
    'groups' => array
    (
        'authors',
    ),
									        
    // Root node definition		
    'root' => array
    (
        'name' => '__HOSTNAME__',
	'title' => '__HOSTTITLE__',
	'component' => 'net.nehmer.blog',
	'acl' => array
	(
	    'authors' => array
	    (
	        'midgard:owner' => MIDCOM_PRIVILEGE_ALLOW,
	    ),
	),
	'parameters' => array
	(
	    'net.nehmer.blog' => array
	    (
	        // Configuration more suitable for blog usage (instead of the more news listing -like defaults)
		'index_entries' => 8,
		'comments_enable' => true,
		'api_metaweblog_enable' => true,
		'view_in_url' => false,
		'index_fulltext' => true,
	    ),
	),
	// Child node definitions
	'nodes' => array
	(
	    'comments' => array
	    (
	        'name' => 'comments',
		'title' => '__HOSTTITLE__ comments',
		'component' => 'net.nehmer.comments',
		'acl' => array
		(
		    'authors' => array
		    (
		        'midgard:delete' => MIDCOM_PRIVILEGE_ALLOW,
		    ),
		),
		'parameters' => array
		(
		    'net.nehmer.comments' => array
		    (
		        'allow_anonymous' => true,
		    ),
		),
            ),
	),
    ),
),


