# Coding Standards And Quality Gates

## Purpose

Set the floor early so the codebase does not become a landfill that later needs a cleanup initiative with a heroic name.

## Baseline stack

- PHP 8.4+
- current stable Laravel
- MySQL as the primary relational store
- queues for long-running imports and generators

## Coding standards

- prefer Laravel conventions over homemade frameworks
- use clear names, not clever names
- keep classes small and specific
- add `declare(strict_types=1);` to owned PHP files
- use DTOs or value objects at boundaries
- avoid passing loose nested arrays through the system
- use strict types in owned PHP files where practical
- keep prompt assets and schemas explicit and versioned

## Required quality tools

- Pint: required locally and in CI
- PHPStan: required at a fairly high level from day one
- Rector: required with a deliberately small explicit ruleset
- Pest: default test framework
- Pest architecture tests: mandatory

## PHPStan stance

- start strict enough to catch drift early
- no baseline as a hiding place for fresh mess

## Rector stance

- use Rector to enforce safe modernization and consistency
- include the rule that requires and fixes `declare(strict_types=1);`
- do not enable giant speculative rulesets
- every active rule must pay rent

Working rule:

- generated PHP code MUST include `declare(strict_types=1);` from the start
- Rector may enforce this, but it is primarily a backstop, not the primary author

## Prompt and skill assets

- store prompt and skill definitions in versioned application paths
- treat them like code: reviewed, named, tested, and versioned
- no inline mega-prompts buried in jobs or controllers

## CI gates

Every branch MUST pass:

1. formatting
2. static analysis
3. Pest test suite
4. Pest architecture tests

## Review checklist

- follows Laravel conventions
- introduces no unnecessary abstraction
- adds or updates tests
- keeps prompt or schema changes versioned

## Acceptance checks

- a new contributor knows which tools are mandatory
- boundary drift can fail CI automatically
