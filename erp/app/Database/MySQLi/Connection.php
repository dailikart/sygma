<?php

namespace App\Database\MySQLi;

/**
 * MySQLi driver used in multi-company mode, where every table has a company prefix.
 *
 * CodeIgniter keeps the table aliases of a query ("invoices i" → "i") in one list
 * per connection and empties it whenever any other query runs. Without a prefix
 * that never matters; with a prefix, a query built while another one ran would
 * read "i.id" as a table named "i" and prefix it ("t_abc_i.id"). Here the list
 * only grows, so an alias is never mistaken for a table. (A real table name is
 * never used as an alias in this application.)
 */
class Connection extends \CodeIgniter\Database\MySQLi\Connection
{
    public function setAliasedTables(array $aliases)
    {
        if ($aliases === []) {
            return $this;
        }

        return parent::setAliasedTables(array_values(array_unique(array_merge($this->aliasedTables, $aliases))));
    }

    public function getPlatform(): string
    {
        return 'MySQLi';
    }
}
