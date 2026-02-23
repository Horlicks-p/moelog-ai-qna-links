import os
import struct

def _unescape_po(s):
    # Minimal PO unescape: \" \\n \\t \\r \\\\
    return (s
        .replace('\\\\', '\\')
        .replace('\\n', '\n')
        .replace('\\t', '\t')
        .replace('\\r', '\r')
        .replace('\\"', '"')
    )

def generate_mo_file(po_file, mo_file):
    print(f"Processing {po_file} -> {mo_file}")
    with open(po_file, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    messages = {}

    entry = {
        "msgctxt": None,
        "msgid": None,
        "msgid_plural": None,
        "msgstr": {},
        "fuzzy": False,
        "obsolete": False,
    }
    current = None

    def flush_entry():
        nonlocal entry
        if entry["obsolete"]:
            entry = {
                "msgctxt": None,
                "msgid": None,
                "msgid_plural": None,
                "msgstr": {},
                "fuzzy": False,
                "obsolete": False,
            }
            return

        if entry["msgid"] is None:
            entry = {
                "msgctxt": None,
                "msgid": None,
                "msgid_plural": None,
                "msgstr": {},
                "fuzzy": False,
                "obsolete": False,
            }
            return

        if entry["fuzzy"]:
            entry = {
                "msgctxt": None,
                "msgid": None,
                "msgid_plural": None,
                "msgstr": {},
                "fuzzy": False,
                "obsolete": False,
            }
            return

        msgid = entry["msgid"]
        if entry["msgctxt"] is not None:
            msgid = entry["msgctxt"] + "\x04" + msgid

        if entry["msgid_plural"] is not None:
            # Plural: key is singular\0plural
            key = msgid + "\x00" + entry["msgid_plural"]
            # Values: msgstr[0]\0msgstr[1]...
            if entry["msgstr"]:
                max_index = max(entry["msgstr"].keys())
                parts = []
                for i in range(max_index + 1):
                    parts.append(entry["msgstr"].get(i, ""))
                val = "\x00".join(parts)
                messages[key] = val
        else:
            val = entry["msgstr"].get(0, "")
            messages[msgid] = val

        entry = {
            "msgctxt": None,
            "msgid": None,
            "msgid_plural": None,
            "msgstr": {},
            "fuzzy": False,
            "obsolete": False,
        }

    for raw_line in lines:
        line = raw_line.strip()
        if not line:
            flush_entry()
            current = None
            continue

        if line.startswith("#~"):
            entry["obsolete"] = True
            continue

        if line.startswith("#,"):
            if "fuzzy" in line:
                entry["fuzzy"] = True
            continue

        if line.startswith("#"):
            continue

        if line.startswith("msgctxt "):
            flush_entry()
            current = "msgctxt"
            raw = line[8:].strip()
            if raw.startswith('"') and raw.endswith('"'):
                entry["msgctxt"] = _unescape_po(raw[1:-1])
            else:
                entry["msgctxt"] = ""
            continue

        if line.startswith("msgid "):
            flush_entry()
            current = "msgid"
            raw = line[6:].strip()
            if raw.startswith('"') and raw.endswith('"'):
                entry["msgid"] = _unescape_po(raw[1:-1])
            else:
                entry["msgid"] = ""
            continue

        if line.startswith("msgid_plural "):
            current = "msgid_plural"
            raw = line[13:].strip()
            if raw.startswith('"') and raw.endswith('"'):
                entry["msgid_plural"] = _unescape_po(raw[1:-1])
            else:
                entry["msgid_plural"] = ""
            continue

        if line.startswith("msgstr["):
            idx_end = line.find("]")
            if idx_end != -1:
                idx = int(line[7:idx_end])
                current = f"msgstr[{idx}]"
                raw = line[idx_end + 1 :].strip()
                if raw.startswith('"') and raw.endswith('"'):
                    entry["msgstr"][idx] = _unescape_po(raw[1:-1])
                else:
                    entry["msgstr"][idx] = ""
            continue

        if line.startswith("msgstr "):
            current = "msgstr[0]"
            raw = line[7:].strip()
            if raw.startswith('"') and raw.endswith('"'):
                entry["msgstr"][0] = _unescape_po(raw[1:-1])
            else:
                entry["msgstr"][0] = ""
            continue

        if line.startswith('"') and line.endswith('"'):
            content = _unescape_po(line[1:-1])
            if current == "msgctxt":
                entry["msgctxt"] = (entry["msgctxt"] or "") + content
            elif current == "msgid":
                entry["msgid"] = (entry["msgid"] or "") + content
            elif current == "msgid_plural":
                entry["msgid_plural"] = (entry["msgid_plural"] or "") + content
            elif current and current.startswith("msgstr["):
                idx = int(current[7:-1])
                entry["msgstr"][idx] = entry["msgstr"].get(idx, "") + content

    flush_entry()

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
