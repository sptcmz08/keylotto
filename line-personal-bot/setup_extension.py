import os
import zipfile
import urllib.request
import time
from pathlib import Path

def get_extension():
    EXTENSION_ID = "ophjlpahpchlmihnnnihgmmeilfjmjjc"
    # Constructing URL that pretends to be a Chrome request
    URL = f"https://clients2.google.com/service/update2/crx?response=redirect&prodversion=99.0&acceptformat=crx2,crx3&x=id%3D{EXTENSION_ID}%26uc"
    
    DEST_DIR = Path("line_extension")
    ZIP_PATH = Path("line_extension.zip")

    if DEST_DIR.exists() and (DEST_DIR / "manifest.json").exists():
        print("[+] LINE Chrome extension already exists at:", DEST_DIR)
        return

    print("[*] Downloading LINE extension CRX...")
    req = urllib.request.Request(
        URL,
        headers={
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36'
        }
    )
    with urllib.request.urlopen(req) as response:
        with open(ZIP_PATH, 'wb') as f:
            f.write(response.read())

    print("[*] Extracting extension...")
    DEST_DIR.mkdir(exist_ok=True)
    try:
        with zipfile.ZipFile(ZIP_PATH, 'r') as zip_ref:
            zip_ref.extractall(DEST_DIR)
    except zipfile.BadZipFile:
        print("[!] Downloaded file is not a valid zip archive. Please check the network.")
        return
        
    # CRX files contain a 500-byte header (mostly PK...) sometimes. 
    # Actually Chrome Web Store crx3 can be directly unzipped by Python 3.8+ if it contains PK header.
    # If standard unzip fails, users can manually download it using CRX Extractor extensions.
    
    if ZIP_PATH.exists():
        ZIP_PATH.unlink()
        
    # ---------- PATCH MANIFEST.JSON FOR LTSM (SharedArrayBuffer) ----------
    manifest_path = DEST_DIR / "manifest.json"
    if manifest_path.exists():
        import json
        with open(manifest_path, 'r', encoding='utf-8') as mf:
            data = json.load(mf)
            
        data["cross_origin_embedder_policy"] = {"value": "require-corp"}
        data["cross_origin_opener_policy"] = {"value": "same-origin"}
        
        with open(manifest_path, 'w', encoding='utf-8') as mf:
            json.dump(data, mf, indent=2, ensure_ascii=False)
        
    print("[+] Extension successfully extracted to 'line_extension' folder.")

if __name__ == "__main__":
    get_extension()
