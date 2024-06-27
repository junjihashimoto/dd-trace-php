--TEST--
Remove credentials from repository URL
--ENV--
DD_TAGS=git.commit.sha:123456,git.repository_url:https://u:t@github.com/user/repo_new
DD_TRACE_GENERATE_ROOT_SPAN=0
--INI--
datadog.trace.git_metadata_enabled=1
--FILE--
<?php

ini_set('datadog.trace.git_metadata_enabled', 1);

$rootSpan = \DDTrace\start_span();
$internalSpan = \DDTrace\start_span();

\DDTrace\close_span();
\DDTrace\close_span();

var_dump(dd_trace_serialize_closed_spans());

?>
--EXPECTF--
array(2) {
  [0]=>
  array(10) {
    ["trace_id"]=>
    string(%d) "%d"
    ["span_id"]=>
    string(%d) "%d"
    ["start"]=>
    int(%d)
    ["duration"]=>
    int(%d)
    ["name"]=>
    string(62) "git_metadata_injection_remove_credentials_from_global_tags.php"
    ["resource"]=>
    string(62) "git_metadata_injection_remove_credentials_from_global_tags.php"
    ["service"]=>
    string(62) "git_metadata_injection_remove_credentials_from_global_tags.php"
    ["type"]=>
    string(3) "cli"
    ["meta"]=>
    array(7) {
      ["runtime-id"]=>
      string(36) "%s"
      ["git.commit.sha"]=>
      string(6) "123456"
      ["git.repository_url"]=>
      string(32) "https://github.com/user/repo_new"
      ["_dd.p.dm"]=>
      string(2) "-0"
      ["_dd.git.commit.sha"]=>
      string(6) "123456"
      ["_dd.git.repository_url"]=>
      string(32) "https://github.com/user/repo_new"
      ["_dd.p.tid"]=>
      string(16) "%s"
    }
    ["metrics"]=>
    array(4) {
      ["process_id"]=>
      float(%d)
      ["_dd.agent_psr"]=>
      float(1)
      ["_sampling_priority_v1"]=>
      float(1)
      ["php.compilation.total_time_ms"]=>
      float(%f)
    }
  }
  [1]=>
  array(10) {
    ["trace_id"]=>
    string(%d) "%d"
    ["span_id"]=>
    string(%d) "%d"
    ["parent_id"]=>
    string(%d) "%d"
    ["start"]=>
    int(%d)
    ["duration"]=>
    int(%d)
    ["name"]=>
    string(0) ""
    ["resource"]=>
    string(0) ""
    ["service"]=>
    string(62) "git_metadata_injection_remove_credentials_from_global_tags.php"
    ["type"]=>
    string(3) "cli"
    ["meta"]=>
    array(2) {
      ["git.commit.sha"]=>
      string(6) "123456"
      ["git.repository_url"]=>
      string(32) "https://github.com/user/repo_new"
    }
  }
}
