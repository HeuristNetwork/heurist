# Coding Style

## Coding Standards

Run `vendor/bin/ecs` to format your code. See [Easy Coding Standard](https://github.com/ecsphp/ecs).
Easy Coding Standard (ECS) is a single tool that runs [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) rules together, configured through one simple PHP file.

## Static Analysis

Run `vendor/bin/phpstan analyse`. See [PHPStan](https://phpstan.org/).
PHPStan focuses on finding errors in your code without actually running it. It catches whole classes of bugs even before you write tests for the code. It moves PHP closer to compiled languages in the sense that the correctness of each line of the code can be checked before you run the actual line.

PHPStan allows you to declare the currently reported list of errors as "the baseline" and cause it not being reported in subsequent runs. It allows you to be interested in violations only in new and changed code.
Current erros are set in the baseline file `phpstan-baseline.neon`.
Next time you run PHPStan, the errors in the baseline will be skipped in the analysis result. You can manage the baseline manually by editing the file, or generate the whole file again by running PHPStan with --generate-baseline.
