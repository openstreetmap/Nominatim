"""
Preprocessing of SQL files.
"""
import jinja2


def _get_partitions(conn):
    """ Get the set of partitions currently in use.
    """
    with conn.cursor() as cur:
        cur.execute('SELECT DISTINCT partition FROM country_name')
        partitions = set([0])
        for row in cur:
            partitions.add(row[0])

    return partitions


def _get_tables(conn):
    """ Return the set of tables currently in use.
        Only includes non-partitioned
    """
    with conn.cursor() as cur:
        cur.execute("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")

        return set((row[0] for row in list(cur)))

class SQLPreprocessor: # pylint: disable=too-few-public-methods
    """ A environment for preprocessing SQL files from the
        lib-sql directory.

        The preprocessor provides a number of default filters and variables.
        The variables may be overwritten when rendering an SQL file.

        The preprocessing is currently based on the jinja2 templating library
        and follows its syntax.
    """

    def __init__(self, conn, config, sqllib_dir):
        self.env = jinja2.Environment(autoescape=False,
                                      loader=jinja2.FileSystemLoader(str(sqllib_dir)))

        db_info = {}
        db_info['partitions'] = _get_partitions(conn)
        db_info['tables'] = _get_tables(conn)
        db_info['reverse_only'] = 'search_name' not in db_info['tables']

        self.env.globals['config'] = config
        self.env.globals['db'] = db_info
        self.env.globals['modulepath'] = config.DATABASE_MODULE_PATH or \
                                         str((config.project_dir / 'module').resolve())


    def run_sql_file(self, conn, name, **kwargs):
        """ Execute the given SQL file on the connection. The keyword arguments
            may supply additional parameters for preprocessing.
        """
        sql = self.env.get_template(name).render(**kwargs)

        with conn.cursor() as cur:
            cur.execute(sql)
        conn.commit()