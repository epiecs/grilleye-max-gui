from pprint import pprint
import esptool
import re
import string

file_name = "grilleyemax.dump"
re_phone_id = r"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}"

serial_ports = esptool.get_port_list()
esp_32 = esptool.get_default_connected_device(serial_list=serial_ports, connect_attempts=2, initial_baud=115200, port=None)

if esp_32:
    pprint(f"========== Found ESP32 on {esp_32.serial_port}")
    pprint(f"========== Dumping 1MB of firmware to {file_name}")
    
    esptool.main(["--baud", "115200", "--port" , esp_32.serial_port, "read_flash", "0x0", "0x100000", file_name])
    
    pprint(f"========== Dump done")
    
    with open(file_name, errors="ignore") as firmwaredump:
        
        text_dump = firmwaredump.read()
        matches = re.findall(re_phone_id, text_dump)
        
        print(f"Found {len(matches)} matches. Usually the last match is the right one.")
        
        for match in matches:
            print(match)
else:
    print("No esp32 found. Are you sure that it is connected and that you have installed the correct drivers?")