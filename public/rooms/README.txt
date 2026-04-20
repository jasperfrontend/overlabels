Drop per-room assets into public/rooms/{N}/{tiles|objects|sounds}/.

Structure:
  public/rooms/1/tiles/*.png      (floor/background tiles)
  public/rooms/1/objects/*.png    (overlay decorations - not editable in v1)
  public/rooms/1/sounds/*.ogg     (entry-triggered sounds - not editable in v1)

The dev-only room builder at /dev/room-builder/{N} scans these folders
and writes its output to resources/js/rooms/{N}.json.
