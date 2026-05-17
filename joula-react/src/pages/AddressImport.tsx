import React, { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Box,
  Typography,
  Button,
  Alert,
  Paper,
  Table,
  TableHead,
  TableBody,
  TableRow,
  TableCell,
  TableContainer,
  LinearProgress,
  Divider,
  Chip,
  MenuItem,
  FormControl,
  InputLabel,
  Select,
} from '@mui/material'
import UploadFileIcon from '@mui/icons-material/UploadFile'
import DownloadIcon from '@mui/icons-material/Download'
import apiService from '@/services/api'
import { ImportAddressesResponse, Masjid } from '@/types'
import { useAuth } from '@/context/AuthContext'

const CSV_TEMPLATE_HEADERS = [
  'Name',
  'H_No',
  'Apt_No',
  'St_Name',
  'City',
  'State',
  'Zip',
  'Comments',
  'locality',
  'Last_Visit',
  'Coordinates',
  'Verified',
]

const CSV_EXAMPLE_ROW = [
  'John Smith',
  '123',
  '',
  'Maple St',
  'Atlanta',
  'GA',
  '30301',
  '',
  'Eastside',
  '',
  '33.7490,-84.3880',
  'N',
]

function toCsvCell(value: string) {
  const normalized = value ?? ''
  if (/[",\r\n]/.test(normalized)) {
    return `"${normalized.replace(/"/g, '""')}"`
  }
  return normalized
}

function parseCsvLine(line: string): string[] {
  const values: string[] = []
  let current = ''
  let inQuotes = false

  for (let i = 0; i < line.length; i += 1) {
    const char = line[i]

    if (char === '"') {
      if (inQuotes && line[i + 1] === '"') {
        current += '"'
        i += 1
      } else {
        inQuotes = !inQuotes
      }
      continue
    }

    if (char === ',' && !inQuotes) {
      values.push(current)
      current = ''
      continue
    }

    current += char
  }

  values.push(current)
  return values
}

function downloadTemplate() {
  const rows = [CSV_TEMPLATE_HEADERS, CSV_EXAMPLE_ROW]
  const csv = rows.map((row) => row.map((value) => toCsvCell(value)).join(',')).join('\r\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'address_import_template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

interface PreviewRow {
  [key: string]: string
}

const PREVIEW_HEADER_LABELS: Record<string, string> = {
  name: 'Name',
  houseno: 'H_No',
  aptno: 'Apt_No',
  streetname: 'St_Name',
  city: 'City',
  state: 'State',
  zip: 'Zip',
  comments: 'Comments',
  locality: 'locality',
  lastvisit: 'Last_Visit',
  coordinates: 'Coordinates',
  verified: 'Verified',
}

function formatPreviewHeader(header: string) {
  const normalized = header.trim().toLowerCase().replace(/[^a-z0-9]+/g, '')
  return PREVIEW_HEADER_LABELS[normalized] || header
}

function parseCsvPreview(file: File): Promise<{ headers: string[]; rows: PreviewRow[] }> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = (e) => {
      const text = (e.target?.result as string) || ''
      const lines = text.split(/\r?\n/).filter((l) => l.trim() !== '')
      if (lines.length === 0) return resolve({ headers: [], rows: [] })
      const headers = parseCsvLine(lines[0]).map((h) => h.trim().replace(/^\uFEFF/, ''))
      const rows: PreviewRow[] = []
      for (let i = 1; i < Math.min(lines.length, 11); i++) {
        const values = parseCsvLine(lines[i])
        const row: PreviewRow = {}
        headers.forEach((h, idx) => {
          row[h] = (values[idx] || '').trim()
        })
        rows.push(row)
      }
      resolve({ headers, rows })
    }
    reader.onerror = () => reject(new Error('Failed to read file'))
    reader.readAsText(file)
  })
}

const AddressImport: React.FC = () => {
  const navigate = useNavigate()
  const { user } = useAuth()
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<{ headers: string[]; rows: PreviewRow[] } | null>(null)
  const [loading, setLoading] = useState(false)
  const [loadingMasjids, setLoadingMasjids] = useState(false)
  const [ownedMasjids, setOwnedMasjids] = useState<Masjid[]>([])
  const [selectedMasjid, setSelectedMasjid] = useState('')
  const [result, setResult] = useState<ImportAddressesResponse | null>(null)
  const [validationResult, setValidationResult] = useState<ImportAddressesResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!user?.id) {
      setOwnedMasjids([])
      setSelectedMasjid('')
      return
    }

    setLoadingMasjids(true)
    apiService
      .getMasjids({ mine: true })
      .then((masjids) => {
        setOwnedMasjids(masjids)
        setSelectedMasjid('')
      })
      .catch(() => {
        setOwnedMasjids([])
        setSelectedMasjid('')
      })
      .finally(() => {
        setLoadingMasjids(false)
      })
  }, [user?.id])

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null
    setSelectedFile(file)
    setResult(null)
    setValidationResult(null)
    setError(null)
    setPreview(null)
    if (!file) return
    if (!file.name.toLowerCase().endsWith('.csv')) {
      setError('Only CSV files are accepted.')
      return
    }
    try {
      const p = await parseCsvPreview(file)
      setPreview(p)
    } catch {
      setError('Could not read file for preview.')
    }
  }

  const handleValidate = async () => {
    if (!selectedFile) return
    if (!selectedMasjid) {
      setError('Select a masjid before importing addresses.')
      return
    }
    setLoading(true)
    setError(null)
    setResult(null)
    setValidationResult(null)
    try {
      const res = await apiService.importAddresses(selectedFile, selectedMasjid, { validateOnly: true })
      setValidationResult(res)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Upload failed'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  const handleConfirmImport = async () => {
    if (!selectedFile) return
    if (!selectedMasjid) {
      setError('Select a masjid before importing addresses.')
      return
    }
    setLoading(true)
    setError(null)
    setResult(null)
    try {
      const res = await apiService.importAddresses(selectedFile, selectedMasjid, { ignoreErrors: true })
      setResult(res)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Import failed'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  const handleIgnoreAndImport = async () => {
    if (!selectedFile) return
    if (!selectedMasjid) {
      setError('Select a masjid before importing addresses.')
      return
    }
    setLoading(true)
    setError(null)
    setResult(null)
    try {
      const res = await apiService.importAddresses(selectedFile, selectedMasjid, { ignoreErrors: true })
      setResult(res)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Import failed'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  const handleChooseFile = () => {
    setSelectedFile(null)
    setPreview(null)
    setResult(null)
    setValidationResult(null)
    setError(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
    fileInputRef.current?.click()
  }

  return (
    <Box sx={{ p: 2, maxWidth: 900, mx: 'auto' }}>
      <Typography variant="h5" gutterBottom>
        Import Addresses from CSV
      </Typography>

      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Upload a CSV file to bulk-add addresses. Rows with missing coordinates will appear on the{' '}
        <strong>Fix Missing Coordinates</strong> page for geocoding. You will only see addresses
        you have uploaded. The selected masjid from the dropdown will be attached automatically,
        so the CSV template does not need a masjid column.
      </Typography>

      {!loadingMasjids && ownedMasjids.length === 0 && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          Add a masjid first. Imported addresses must be associated with one of your approved masjids.
        </Alert>
      )}

      <Box sx={{ mb: 2 }}>
        <FormControl fullWidth disabled={loadingMasjids || ownedMasjids.length === 0 || loading}>
          <InputLabel>Masjid *</InputLabel>
          <Select
            value={selectedMasjid}
            label="Masjid *"
            onChange={(e) => setSelectedMasjid(e.target.value)}
            displayEmpty
          >
            <MenuItem value="">
              <em>Select a Masjid</em>
            </MenuItem>
            {ownedMasjids.map((masjid) => (
              <MenuItem key={masjid.id} value={masjid.name}>
                {masjid.name}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
      </Box>

      {/* Actions bar */}
      <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap', mb: 3 }}>
        <Button
          variant="outlined"
          startIcon={<DownloadIcon />}
          onClick={downloadTemplate}
          size="small"
        >
          Download CSV Template
        </Button>
        <Button
          variant="contained"
          startIcon={<UploadFileIcon />}
          onClick={handleChooseFile}
          size="small"
          disabled={loadingMasjids || ownedMasjids.length === 0 || loading}
        >
          {selectedFile ? 'Change File' : 'Choose CSV File'}
        </Button>
        {selectedFile && !loading && (
          <Button
            variant="contained"
            color="warning"
            size="small"
            onClick={handleValidate}
            disabled={(!!error && !preview) || !selectedMasjid}
          >
            Validate File
          </Button>
        )}
        {selectedFile && !loading && validationResult?.canImport && (
          <Button
            variant="contained"
            color="success"
            size="small"
            onClick={handleConfirmImport}
            disabled={!selectedMasjid}
          >
            Confirm Import All
          </Button>
        )}
        {selectedFile && !loading && validationResult && validationResult.errors.length > 0 && (
          <Button
            variant="contained"
            color="warning"
            size="small"
            onClick={handleIgnoreAndImport}
            disabled={!selectedMasjid || validationResult.inserted === 0}
          >
            Ignore Errors &amp; Import Valid Rows
          </Button>
        )}
      </Box>

      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        accept=".csv"
        style={{ display: 'none' }}
        onChange={handleFileChange}
      />

      {/* Selected file name */}
      {selectedFile && (
        <Typography variant="body2" sx={{ mb: 1 }}>
          Selected: <strong>{selectedFile.name}</strong>
        </Typography>
      )}

      {loading && <LinearProgress sx={{ mb: 2 }} />}

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {/* Validation result */}
      {validationResult && !result && (
        <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
          <Typography variant="subtitle1" gutterBottom>
            Validation Result
          </Typography>
          <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', mb: 1 }}>
            <Chip label={`${validationResult.inserted} ready to import`} color={validationResult.canImport ? 'success' : 'warning'} size="small" />
            <Chip label={`${validationResult.skipped} duplicates`} color="default" size="small" />
            {validationResult.errors.length > 0 && (
              <Chip label={`${validationResult.errors.length} errors`} color="error" size="small" />
            )}
          </Box>

          {validationResult.canImport ? (
            <Alert severity="success" sx={{ mb: 2, fontWeight: 'bold' }}>
              ✓ Validation passed! {validationResult.inserted} addresses are ready to import.
              <br />
              <strong>Next step: Click the "Confirm Import All" button below to save these addresses to your account.</strong>
            </Alert>
          ) : (
            <Alert severity="warning" sx={{ mb: 2, fontWeight: 'bold' }}>
              ⚠ Some rows have validation errors. You can either fix them and validate again, or skip the bad rows and import the valid ones.
            </Alert>
          )}

          {validationResult.errors.length > 0 && (
            <Box sx={{ mb: 1, display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
              <Button
                variant="contained"
                color="warning"
                size="small"
                onClick={handleIgnoreAndImport}
                disabled={loading || !selectedMasjid || validationResult.inserted === 0}
              >
                Ignore Errors &amp; Import Valid Rows
              </Button>
              {validationResult.inserted === 0 && (
                <Typography variant="caption" color="text.secondary">
                  No valid rows are available to import.
                </Typography>
              )}
            </Box>
          )}

          {validationResult.errors.length > 0 && (
            <>
              <Divider sx={{ my: 1 }} />
              <Typography variant="body2" color="error" gutterBottom>
                Row errors:
              </Typography>
              <TableContainer sx={{ maxHeight: 220 }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Row</TableCell>
                      <TableCell>Issue</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {validationResult.errors.map((e, i) => (
                      <TableRow key={i}>
                        <TableCell>{e.row}</TableCell>
                        <TableCell>{e.message}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </>
          )}
        </Paper>
      )}

      {/* Import result */}
      {result && (
        <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
          <Typography variant="subtitle1" gutterBottom>
            Import Result
          </Typography>
          <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', mb: 1 }}>
            <Chip label={`${result.inserted} inserted`} color="success" size="small" />
            <Chip label={`${result.skipped} skipped (duplicates)`} color="default" size="small" />
            {result.errors.length > 0 && (
              <Chip label={`${result.errors.length} errors`} color="error" size="small" />
            )}
          </Box>

          {result.errors.length > 0 && (
            <>
              <Divider sx={{ my: 1 }} />
              <Typography variant="body2" color="error" gutterBottom>
                Row errors:
              </Typography>
              <TableContainer sx={{ maxHeight: 200 }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Row</TableCell>
                      <TableCell>Issue</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {result.errors.map((e, i) => (
                      <TableRow key={i}>
                        <TableCell>{e.row}</TableCell>
                        <TableCell>{e.message}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </>
          )}

          {result.inserted > 0 && (
            <Box sx={{ mt: 2 }}>
              <Button
                variant="outlined"
                size="small"
                onClick={() => navigate('/missing-coordinates')}
              >
                Go to Fix Missing Coordinates →
              </Button>
            </Box>
          )}
        </Paper>
      )}

      {/* CSV preview */}
      {preview && preview.headers.length > 0 && !result && !validationResult?.canImport && (
        <Paper variant="outlined" sx={{ p: 2 }}>
          <Typography variant="subtitle2" gutterBottom>
            Preview (first {preview.rows.length} data rows)
          </Typography>
          <TableContainer sx={{ overflowX: 'auto' }}>
            <Table size="small" sx={{ minWidth: 600 }}>
              <TableHead>
                <TableRow>
                  {preview.headers.map((h) => (
                    <TableCell key={h} sx={{ whiteSpace: 'nowrap', fontWeight: 'bold' }}>
                      {formatPreviewHeader(h)}
                    </TableCell>
                  ))}
                </TableRow>
              </TableHead>
              <TableBody>
                {preview.rows.map((row, i) => (
                  <TableRow key={i}>
                    {preview.headers.map((h) => (
                      <TableCell key={h} sx={{ whiteSpace: 'nowrap' }}>
                        {row[h] || ''}
                      </TableCell>
                    ))}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>

          <Typography variant="caption" color="text.secondary" sx={{ mt: 1, display: 'block' }}>
            Required columns: Name, H_No, St_Name, City, State, Zip, locality
          </Typography>
        </Paper>
      )}

      {!loadingMasjids && ownedMasjids.length === 0 && (
        <Box sx={{ mt: 2 }}>
          <Button variant="outlined" onClick={() => navigate('/masjids/new')}>
            Add New Masjid
          </Button>
        </Box>
      )}
    </Box>
  )
}

export default AddressImport
