# Dynamix Auto Fan Control (Enhanced Fork)

Fork of [unraid/dynamix](https://github.com/unraid/dynamix) by [@mrjacobarussell](https://github.com/mrjacobarussell).

Automatically controls fan speed based on temperature. This fork was built specifically to make Dynamix work correctly with the **Corsair Commander Pro** — fixing two fundamental problems the original has with USB fan controllers — and adds several quality-of-life improvements for all users.

---

## The Corsair Commander Pro problem

The Corsair Commander Pro is a USB device. Linux exposes it through the `corsair_cpro` kernel module as an hwmon device (`/sys/class/hwmon/hwmonN/`). Two things break the original Dynamix plugin:

### Problem 1: hwmon number changes every reboot

USB devices get their `hwmonN` number assigned at boot based on driver init order. Your Commander Pro might be `hwmon2` today and `hwmon5` after the next reboot. The original plugin saves the full path at config time — e.g. `/sys/class/hwmon/hwmon2/pwm1` — and uses it forever. After a reboot where the number changes, fan control silently stops working with no error.

**This fork fixes it** by adding a **Stable device name** field. Set it to `corsaircpro` and `rc.autofan` will find the correct `hwmonN` directory by kernel device name every time it starts, regardless of what number it was assigned that boot.

### Problem 2: Can't use Corsair temp probes as the fan control source

The original plugin only reads temperature from hard drives via `smartctl`. The Commander Pro has onboard temperature probe headers (up to 4 probes) that are exposed as hwmon `temp*_input` files. There was no way to use these to drive fan speed.

**This fork fixes it** by adding a **Temperature source** option. Switch from `Hard drives (smartctl)` to `Hardware sensor probe` and pick any hwmon sensor — including Corsair's probe inputs — from a live dropdown showing current readings.

---

## Install

### Switching from the original Dynamix Auto Fan Control

If you already have the original plugin installed, remove it first — then install this fork. Your fan settings (`.cfg` file on the flash drive) are preserved through the swap.

**Option A — Unraid web UI:**
1. Go to **Plugins** in the Unraid web UI
2. Find **Dynamix System Autofan**, click its icon → **Remove**
3. Go to **Plugins** → **Install Plugin**
4. Paste the URL below and click **Install**

**Option B — Terminal (SSH into Unraid):**
```bash
plugin remove dynamix.system.autofan
plugin install https://raw.githubusercontent.com/mrjacobarussell/dynamix/corsair-commander-pro/unRAIDv6/dynamix.system.autofan.plg
```

### Fresh install

In Unraid, go to **Plugins** → **Install Plugin** and paste:

```
https://raw.githubusercontent.com/mrjacobarussell/dynamix/corsair-commander-pro/unRAIDv6/dynamix.system.autofan.plg
```

---

## Corsair Commander Pro setup

**Prerequisites:** The `corsair_cpro` kernel module must be loaded. Add this to your Unraid `go` file if not already present:

```bash
modprobe corsair_cpro
```

**Configuration:**

1. Open **Utilities → Fan Auto Control** in the Unraid web UI
2. **PWM controller** — select your Corsair entry (shows as `corsaircpro - pwm1`, `pwm2`, etc.)
3. **PWM fan** — dropdown now shows only Corsair fans filtered to that chip. Select the fan sensor you want to monitor RPM on. Use **Detect** to auto-identify which fan spins up when that PWM is cycled.
4. **Temperature source** — switch to `Hardware sensor probe`
5. **Temperature sensor** — pick a Corsair probe from the dropdown (shows live °C readings). Your probes appear as `corsaircpro - temp1_input`, etc.
6. **Stable device name** — type `corsaircpro` — this is the kernel hwmon device name that keeps the paths stable across reboots
7. **Apply**

Fan control will now survive reboots even when the Commander Pro gets a different hwmon number.

---

## All changes in this fork

### Stable USB hwmon path resolution
`rc.autofan` resolves the current hwmon directory by kernel device name at start/stop time. The `hwmon_name` cfg field (e.g. `corsaircpro`) is the stable identifier — no udev rules needed. Works for any USB hwmon device, not just Corsair.

### Hardware sensor probe temperature source
Any hwmon `temp*_input` file can now drive fan speed instead of (or instead of relying on) hard drive temperatures. Useful for case ambient probes, CPU package temp, chipset temp, or any sensor the kernel exposes via hwmon.

### Fan picker dropdown filtered by chip
The PWM fan field is now a dropdown of fan sensors, filtered to the same hwmon chip as the selected PWM controller. Picking a Corsair PWM shows only Corsair fans. Detect auto-selects the matched fan in the dropdown.

### Fan detect bug fix
The original detect logic compared fans across all hwmon chips — a motherboard fan could win the RPM race when a USB controller PWM was being cycled. Fixed to only compare fans on the same chip as the selected PWM.

### Drive filter mode toggle
The drive list can operate as an **include** list (monitor only selected drives) or an **exclude** list (skip selected drives). Useful when you want only specific array drives to influence fan speed.

---

## Contributing / Upstream

A pull request with these changes has been submitted to [unraid/dynamix#3](https://github.com/unraid/dynamix/pull/3).

---

## Tips & usage notes

### Enabling a PWM controller before configuring it

The plugin requires the fan control service to be **Enabled** before the PWM fan dropdown is active. If you just selected a new PWM controller and the fan dropdown is empty or grayed out:

1. Set **Fan control function** to `Enabled`
2. Select your **PWM controller** (e.g. `corsaircpro - pwm5`)
3. Set **PWM fan** to the matching fan (e.g. `fan5`)
4. Click **Apply**

You must apply with the service enabled before the settings take effect. Selecting a controller while the service is disabled and clicking Apply will save the config but not start control.

### Max cooling mode (fans always full speed)

If you want a fan zone to always run at full speed regardless of temperature — useful for hot drives or max airflow scenarios — set the **Drive filter mode** to `Include` and select **no drives**. With an empty include list, no drive temperature is ever read, `HIGHEST_TEMP` stays at 0, and the fan stays at whatever your low/off threshold dictates.

Alternatively, set your **Low temperature threshold** and **High temperature threshold** both to `0` — this forces the fan to full speed immediately since any temperature ≥ 0°C triggers 100%.
