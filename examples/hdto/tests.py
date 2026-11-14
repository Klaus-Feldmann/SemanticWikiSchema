import sys
sys.dont_write_bytecode=True
import unittest
from build_mapping import build
class MappingGeneratorTest(unittest.TestCase):
    def test_ids_and_field_names(self):
        m=build('S21','S22','S23','F1','F2','F3')
        self.assertEqual(m['schemas'],{});self.assertEqual(set(m['schemaIds']),{'S21','S22','S23'})
        self.assertEqual(m['schemaIds']['S21']['properties']['F1']['node'],'production')
        self.assertEqual(m['schemaIds']['S21']['properties']['F3']['predicate'],'hdto:HP9i_has_visual_representation')
    def test_invalid_ids(self):
        for value in ['S0','S01','E1','S'+'9'*19]:
            with self.assertRaises(ValueError):build(value,'S22','S23','A','B','C')
    def test_duplicate_schema_ids(self):
        with self.assertRaises(ValueError):build('S21','S21','S23','A','B','C')
    def test_duplicate_or_empty_fields(self):
        for a,b in [('A',' A '),('','B')]:
            with self.assertRaises(ValueError):build('S21','S22','S23',a,b,'C')
    def test_labels_are_not_schema_keys(self):
        m=build('S21','S22','S23','Erzeuger','Institution','Bild')
        self.assertNotIn('Artwork',m['schemaIds']);self.assertIn('Erzeuger',m['schemaIds']['S21']['properties'])
if __name__=='__main__':unittest.main()
