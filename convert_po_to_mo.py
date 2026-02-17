import os
import struct
import array

def generate_mo_file(po_file, mo_file):
    print(f"Processing {po_file} -> {mo_file}")
    with open(po_file, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    messages = {}
    current_msgid = None
    current_msgstr = None
    in_msgid = False
    in_msgstr = False

    for line in lines:
        line = line.strip()
        if not line:
            continue
        if line.startswith('#'):
            continue

        if line.startswith('msgid '):
            if current_msgid is not None:
                messages[current_msgid] = current_msgstr
            current_msgid = ""
            current_msgstr = ""
            in_msgid = True
            in_msgstr = False
            # Clean string
            raw = line[6:]
            if raw.startswith('"') and raw.endswith('"'):
                current_msgid = raw[1:-1].replace('\\"', '"').replace('\\n', '\n')
        elif line.startswith('msgstr '):
            in_msgid = False
            in_msgstr = True
            raw = line[7:]
            if raw.startswith('"') and raw.endswith('"'):
                current_msgstr = raw[1:-1].replace('\\"', '"').replace('\\n', '\n')
        elif line.startswith('"') and line.endswith('"'):
            content = line[1:-1].replace('\\"', '"').replace('\\n', '\n')
            if in_msgid:
                current_msgid += content
            elif in_msgstr:
                current_msgstr += content

    # Add last message
    if current_msgid is not None:
        messages[current_msgid] = current_msgstr

    # Filter out empty msgid (header) if handled separately or treat as normal empty str key
    # MO files usually store the header under key "" (empty string).
    
    # Sort keys
    keys = sorted(messages.keys())
    
    # Build binary parts
    # validation: no duplicates
    
    # Magic number: 0x950412de
    magic = 0x950412de
    revision = 0
    count = len(keys)
    
    # Offsets
    ids_offset = 28
    strs_offset = 28 + count * 8
    
    # We need to calculate size of key/values to know where data starts
    # keys data starts after strs offsets
    # data_start = strs_offset + count * 8
    
    # Actually, simpler structure:
    # Header (28 bytes)
    # 0: magic
    # 4: revision
    # 8: count
    # 12: offset of original strings table
    # 16: offset of translated strings table
    # 20: size of hash table (0)
    # 24: offset of hash table (28 + 2 * count * 8)
    
    # Tables:
    # Original strings table: count * (length, offset)
    # Translated strings table: count * (length, offset)
    
    # Data area follows
    
    orig_table_offset = 28
    trans_table_offset = 28 + count * 8
    hash_table_offset = 28 + 2 * count * 8
    
    data_offset = hash_table_offset # if hash table size is 0
    
    # Prepare data buffers
    orig_data = bytearray()
    trans_data = bytearray()
    
    # Calculate offsets
    # We will append data to a single buffer and keep track of offsets relative to file start
    
    # First, let's collect all strings
    all_origs = []
    all_trans = []
    
    for k in keys:
        all_origs.append(k.encode('utf-8'))
        all_trans.append(messages[k].encode('utf-8'))
        
    # Calculate initial data_offset
    current_data_pos = data_offset
    
    orig_descriptors = []
    for s in all_origs:
        length = len(s)
        orig_descriptors.append((length, current_data_pos))
        current_data_pos += length + 1 # +1 for null terminator
        
    trans_descriptors = []
    for s in all_trans:
        length = len(s)
        trans_descriptors.append((length, current_data_pos))
        current_data_pos += length + 1
        
    # Write to file
    with open(mo_file, 'wb') as f:
        # Header
        f.write(struct.pack('I', magic))
        f.write(struct.pack('I', revision))
        f.write(struct.pack('I', count))
        f.write(struct.pack('I', orig_table_offset))
        f.write(struct.pack('I', trans_table_offset))
        f.write(struct.pack('I', 0)) # hash size
        f.write(struct.pack('I', hash_table_offset)) # hash offset
        
        # Original strings table
        for length, offset in orig_descriptors:
            f.write(struct.pack('II', length, offset))
            
        # Translated strings table
        for length, offset in trans_descriptors:
            f.write(struct.pack('II', length, offset))
        
        # Data
        for s in all_origs:
            f.write(s)
            f.write(b'\0')
            
        for s in all_trans:
            f.write(s)
            f.write(b'\0')
            
    print("Done.")

if __name__ == '__main__':
    base_dir = r'd:\XAMPP\htdocs\wordpress\wp-content\plugins\moelog-ai-qna-links\languages'
    locales = ['zh_TW', 'en_US', 'ja']
    
    for locale in locales:
        po_path = os.path.join(base_dir, f'moelog-ai-qna-{locale}.po')
        mo_path = os.path.join(base_dir, f'moelog-ai-qna-{locale}.mo')
        
        if os.path.exists(po_path):
            generate_mo_file(po_path, mo_path)
        else:
            print(f"PO file not found: {po_path}")
    
    print("All done!")
