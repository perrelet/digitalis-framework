# digitalis-framework

some abstract objects.

## The WordPress Query Controller

```mermaid
graph TB

    direction TB

    index(["index.php"]) -->
    wp-blog-header(["wp-blog-header.php"])

    wp-blog-header
    -- "wp()" --> wp

    admin-edit(["wp-admin/edit.php"]) -->
    prepare_items(["WP_Posts_List_Table::prepare_items()"]) -->
    admin-posts(["wp_edit_posts_query()\nwp-admin/includes/post.php"])
    -- "wp($query)" --> wp

    wp(["wp($query_vars = '')\nfunctions.php"]) -->
    main(["WP::main($query_vars = '')"])

    main --> parse_request(["WP::parse_request($query_vars = '')"])
    main --> query_posts(["WP::query_posts()"])
    main --> handle_404(["WP::handle_404()"])
    main --> register_globals(["WP::register_globals()"])
    main --> send_headers(["WP::send_headers()"])

```

## The Digitalis Query Controller

Digitalis Framework extends the functionality of WordPress queries rather than overwriting them.

```mermaid
graph TB

    direction TB

    ajax(["wp_ajax_query_{$post_type}"]) -->
    parse-request("global $wp, $wp_query;\n$wp->parse_request();\n$wp_query->query_vars = $wp->query_vars;") -->
    ajax-query("Post_Type::ajax_query") <--> query-args

    pre-get(["pre_get_posts"]) -- "$wp_query" -->
    is-main("Post_Type::is_main_query") -->
    main-query("Post_Type::main_query") <-->
    query-args("Post_Type::get_query_vars") -->
    model-query("Model::query") -->
    get-inst("Model::get_instances")

    pre-get -- "$wp_query" -->
    is-main-admin("Post_Type::is_main_admin_query") -->
    admin-query("Post_Type::admin_query") <-->
    admin-query-args("Post_Type::get_admin_query_vars") -->
    model-query

    


```